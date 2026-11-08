<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\PatientCharge;
use Modules\Foundation\Support\Audit;

class InvoiceService
{
    public const TRANSITIONS = [
        'draft' => ['pending_review', 'approved', 'cancelled'],
        'pending_review' => ['approved', 'cancelled'],
        'approved' => ['issued', 'cancelled'],
        'issued' => ['disputed', 'partially_credited', 'credited', 'overdue', 'paid', 'partially_paid'],
        'overdue' => ['paid', 'partially_paid', 'partially_credited', 'credited', 'disputed'],
        'disputed' => ['issued', 'credited'],
        'partially_paid' => ['paid', 'partially_credited', 'credited', 'overdue'],
        'partially_credited' => ['credited', 'paid'],
        'paid' => [], 'credited' => [], 'cancelled' => [],
    ];

    private function nextNumber(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "finance.{$prefix}_sequence";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'finance', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    /** Build invoice from posted charges — unique(patient_charge_id) prevents double-invoicing. */
    public function generate(int $patientId, int $branchId, array $chargeIds, int $userId, ?int $admissionId = null, ?string $type = null): Invoice
    {
        return DB::transaction(function () use ($patientId, $branchId, $chargeIds, $userId, $admissionId, $type) {
            $charges = PatientCharge::whereIn('id', $chargeIds)->where('patient_id', $patientId)->where('branch_id', $branchId)->lockForUpdate()->get();
            abort_unless($charges->count() === count($chargeIds), 422, 'Invalid or foreign charge.');
            abort_if($charges->contains(fn ($c) => ! in_array($c->status, ['posted'])), 422, 'Only posted charges can be invoiced.');

            $sub = $tax = '0';
            foreach ($charges as $c) { $sub = bcadd($sub, bcsub(bcmul((string) $c->quantity, $c->unit_price, 2), $c->discount, 2), 2); $tax = bcadd($tax, $c->tax, 2); }
            $discount = $charges->sum(fn ($c) => (float) $c->discount);
            $total = bcadd($sub, $tax, 2);

            $inv = Invoice::create([
                'invoice_number' => $this->nextNumber('INV', 'invoices', 'invoice_number'),
                'patient_id' => $patientId, 'branch_id' => $branchId, 'admission_id' => $admissionId,
                'type' => $type ?? ($admissionId ? 'inpatient' : 'outpatient'),
                'subtotal' => $sub, 'discount' => $discount, 'tax' => $tax, 'total' => $total,
                'status' => 'draft', 'created_by' => $userId,
            ]);
            foreach ($charges as $c) {
                $inv->items()->create([
                    'patient_charge_id' => $c->id, 'service_name' => $c->service?->name ?? $c->description ?? 'Service',
                    'service_date' => $c->service_date, 'quantity' => $c->quantity,
                    'unit_price' => $c->unit_price, 'discount' => $c->discount, 'tax' => $c->tax, 'line_total' => $c->total,
                ]);
            }
            $inv->statusHistories()->create(['to_status' => 'draft', 'changed_by' => $userId]);
            Audit::record('finance.invoice_created', $inv->patient, null, ['inv' => $inv->invoice_number, 'total' => (string) $total], $branchId);
            return $inv;
        });
    }

    public function transition(Invoice $inv, string $to, int $userId, ?string $reason = null): Invoice
    {
        abort_unless(in_array($to, static::TRANSITIONS[$inv->status] ?? []), 422, "Cannot move {$inv->status}→{$to}.");
        abort_if($to === 'cancelled' && ! $reason, 422, 'Reason required.');
        DB::transaction(function () use ($inv, $to, $userId, $reason) {
            $inv->statusHistories()->create(['from_status' => $inv->status, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
            $inv->update(['status' => $to] + match ($to) {
                'issued' => ['issue_date' => today(), 'issued_by' => $userId, 'due_date' => now()->addDays(30)],
                'cancelled' => ['cancel_reason' => $reason],
                default => [],
            });
        });
        Audit::record("finance.invoice_{$to}", $inv->patient, null, ['inv' => $inv->invoice_number], $inv->branch_id);
        return $inv->fresh();
    }

    /** Issued credit note reduces outstanding — never deletes the invoice. */
    public function issueCreditNote(Invoice $inv, float $amount, string $reason, string $type, int $userId): CreditNote
    {
        abort_unless(in_array($inv->status, ['issued', 'overdue', 'partially_paid', 'disputed']), 422, 'Only issued invoices can be credited.');
        $outstanding = $this->outstanding($inv);
        abort_if(bccomp((string) $amount, (string) $outstanding, 2) > 0, 422, 'Credit exceeds outstanding.');
        return DB::transaction(function () use ($inv, $amount, $reason, $type, $userId, $outstanding) {
            $cn = $inv->creditNotes()->create([
                'credit_number' => $this->nextNumber('CN', 'credit_notes', 'credit_number'),
                'patient_id' => $inv->patient_id, 'branch_id' => $inv->branch_id,
                'type' => $type, 'amount' => $amount, 'reason' => $reason,
                'status' => 'issued', 'created_by' => $userId, 'approved_by' => $userId,
            ]);
            $newStatus = bccomp((string) $amount, (string) $outstanding, 2) === 0 ? 'credited' : 'partially_credited';
            $inv->update(['status' => $newStatus]);
            Audit::record('finance.credit_note_issued', $inv->patient, null, ['cn' => $cn->credit_number, 'amount' => (string) $amount], $inv->branch_id);
            return $cn;
        });
    }

    /** outstanding = total − issued credit notes (no payments yet — M3). */
    public function outstanding(Invoice $inv): float
    {
        $credited = (float) $inv->creditNotes()->where('status', 'issued')->sum('amount');
        $paid = (float) $inv->paymentAllocations()->where('status', 'allocated')
            ->whereHas('payment', fn ($q) => $q->where('status', 'successful'))->sum('amount');
        return max(0, (float) $inv->total - $credited - $paid);
    }

    /** Aging bucket from due_date vs today — only for issued/unpaid invoices. */
    public function agingBucket(Invoice $inv): string
    {
        if (! $inv->due_date || ! in_array($inv->status, ['issued', 'overdue', 'partially_paid'])) return 'n/a';
        $days = $inv->due_date->diffInDays(now(), false);
        if ($days <= 0) return 'current';
        return match (true) { $days <= 30 => '1-30', $days <= 60 => '31-60', $days <= 90 => '61-90', default => '90+' };
    }

    public function statement(int $patientId, int $branchId): array
    {
        $invs = Invoice::where('patient_id', $patientId)->where('branch_id', $branchId)->whereIn('status', ['issued','overdue','partially_paid','partially_credited','credited','paid'])->with('items', 'creditNotes')->get();
        return [
            'invoices' => $invs,
            'total' => (float) $invs->sum('total'),
            'credited' => (float) $invs->sum(fn ($i) => $i->creditNotes->where('status', 'issued')->sum('amount')),
            'outstanding' => $invs->sum(fn ($i) => $this->outstanding($i)),
        ];
    }
}
