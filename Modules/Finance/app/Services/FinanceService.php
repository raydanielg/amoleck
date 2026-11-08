<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Models\ServicePrice;

class FinanceService
{
    public const PRICE_TRANSITIONS = [
        'draft' => ['pending_approval', 'approved', 'rejected', 'archived'],
        'pending_approval' => ['approved', 'rejected'],
        'approved' => ['scheduled', 'expired', 'archived'],
        'scheduled' => ['approved', 'archived'],
        'expired' => ['archived'],
        'rejected' => [], 'archived' => [],
    ];
    public const CHARGE_TRANSITIONS = [
        'draft' => ['pending_review', 'approved', 'cancelled'],
        'pending_review' => ['approved', 'cancelled'],
        'approved' => ['posted', 'cancelled'],
        'posted' => ['partially_reversed', 'reversed'],
        'partially_reversed' => ['reversed'],
        'reversed' => [], 'cancelled' => [],
    ];

    /** Resolve price: branch-specific approved → global default → null. No silent fallback. */
    public function resolvePrice(int $serviceId, int $branchId, ?string $date = null): ?ServicePrice
    {
        $date = $date ?? today()->toDateString();
        $base = fn ($b) => ServicePrice::where('clinical_service_id', $serviceId)->where('status', 'approved')
            ->where('branch_id', $b)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->latest('effective_from');
        return $base($branchId)->first() ?? $base(null)->first();
    }

    public function transitionPrice(ServicePrice $p, string $to, int $userId, ?string $reason = null): ServicePrice
    {
        abort_unless(in_array($to, static::PRICE_TRANSITIONS[$p->status] ?? []), 422, "Cannot move {$p->status}→{$to}.");
        abort_if($to === 'approved' && $p->created_by === $userId, 422, 'Cannot approve own price.');
        abort_if($to === 'rejected' && ! $reason, 422, 'Rejection reason required.');
        $p->update(['status' => $to, 'reject_reason' => $reason] + ($to === 'approved' ? ['approved_by' => $userId, 'approved_at' => now()] : []));
        Audit::record("finance.price_{$to}", null, null, ['price' => $p->id], $p->branch_id);
        return $p->fresh();
    }

    public function nextChargeNumber(): string
    {
        return DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'finance.charge_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = PatientCharge::where('charge_number', 'like', 'CHG-'.date('Y').'-%')->orderByDesc('charge_number')->value('charge_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => 'finance.charge_sequence'], ['group' => 'finance', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf('CHG-%s-%06d', date('Y'), $seq);
        });
    }

    /** Create charge from a completed service event — idempotent via unique(source, source_id). */
    public function createCharge(array $data, int $userId): PatientCharge
    {
        $price = $this->resolvePrice($data['clinical_service_id'], $data['branch_id'], $data['service_date'] ?? null);
        abort_unless($price, 422, 'No approved price for this service at this branch.');
        $qty = (int) ($data['quantity'] ?? 1);
        $discount = (float) ($data['discount'] ?? 0);
        $subtotal = bcmul((string) $qty, $price->price, 2);
        $afterDiscount = bcsub($subtotal, (string) $discount, 2);
        $tax = bcmul($afterDiscount, bcdiv($price->tax_rate, '100', 4), 2);
        $total = bcadd($afterDiscount, $tax, 2);
        abort_if(bccomp($total, '0', 2) < 0, 422, 'Charge total cannot be negative.');

        return DB::transaction(function () use ($data, $price, $qty, $discount, $tax, $total, $userId) {
            $c = PatientCharge::create($data + [
                'charge_number' => $this->nextChargeNumber(), 'service_price_id' => $price->id,
                'unit_price' => $price->price, 'quantity' => $qty, 'discount' => $discount, 'tax' => $tax,
                'total' => $total, 'currency' => $price->currency,
                'department_id' => $data['department_id'] ?? $price->department_id,
                'status' => 'draft', 'created_by' => $userId,
            ]);
            $c->statusHistories()->create(['to_status' => 'draft', 'changed_by' => $userId]);
            Audit::record('finance.charge_created', $c->patient, null, ['charge' => $c->charge_number, 'total' => (string) $total], $c->branch_id);
            return $c;
        });
    }

    public function transitionCharge(PatientCharge $c, string $to, int $userId, ?string $reason = null): PatientCharge
    {
        $from = $c->status;
        abort_unless(in_array($to, static::CHARGE_TRANSITIONS[$from] ?? []), 422, "Cannot move {$from}→{$to}.");
        DB::transaction(function () use ($c, $to, $from, $userId, $reason) {
            $c->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
            $c->update(['status' => $to] + match ($to) {
                'approved' => ['reviewed_by' => $userId],
                'posted' => ['posted_at' => now()],
                default => [],
            });
        });
        Audit::record("finance.charge_{$to}", $c->patient, ['status' => $from], ['status' => $to], $c->branch_id);
        return $c->fresh();
    }

    /** Reverse a posted charge — preserves original, creates adjustment record. */
    public function reverseCharge(PatientCharge $c, string $reason, int $userId): void
    {
        abort_unless(in_array($c->status, ['posted', 'partially_reversed']), 422, 'Only posted charges can be reversed.');
        DB::transaction(function () use ($c, $reason, $userId) {
            $c->adjustments()->create(['type' => 'reversal', 'amount' => $c->total, 'reason' => $reason, 'requested_by' => $userId, 'approved_by' => $userId]);
            $this->transitionCharge($c, 'reversed', $userId, $reason);
        });
        Audit::record('finance.charge_reversed', $c->patient, null, ['charge' => $c->charge_number], $c->branch_id);
    }

    public function dashboard(int $branchId): array
    {
        $q = PatientCharge::where('branch_id', $branchId);
        return [
            'today' => (clone $q)->whereDate('created_at', today())->count(),
            'pending_review' => (clone $q)->where('status', 'pending_review')->count(),
            'approved' => (clone $q)->where('status', 'approved')->count(),
            'posted' => (clone $q)->where('status', 'posted')->count(),
            'reversed' => (clone $q)->where('status', 'reversed')->count(),
            'posted_total' => (float) (clone $q)->where('status', 'posted')->sum('total'),
        ];
    }

    public function patientSummary(int $patientId, int $branchId): array
    {
        $q = PatientCharge::where('patient_id', $patientId)->where('branch_id', $branchId);
        return [
            'total_charges' => (clone $q)->count(),
            'posted_total' => (float) (clone $q)->where('status', 'posted')->sum('total'),
            'recent' => (clone $q)->with('service:id,name')->latest()->limit(10)->get(),
        ];
    }
}
