<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\InsuranceClaim;
use Modules\Finance\Models\InsurancePreauthorization;
use Modules\Finance\Models\PatientInsuranceMembership;
use Modules\Foundation\Support\Audit;

class InsuranceService
{
    public const CLAIM_TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['acknowledged', 'under_review', 'rejected'],
        'acknowledged' => ['under_review', 'approved', 'partially_approved', 'rejected', 'queried'],
        'under_review' => ['approved', 'partially_approved', 'rejected', 'queried'],
        'queried' => ['submitted'],
        'approved' => ['paid', 'partially_paid', 'closed'],
        'partially_approved' => ['paid', 'partially_paid', 'closed'],
        'partially_paid' => ['paid', 'closed'],
        'rejected' => [], 'paid' => [], 'closed' => [], 'cancelled' => [],
    ];
    public const PA_TRANSITIONS = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['approved', 'partially_approved', 'rejected', 'expired'], 'approved' => [], 'partially_approved' => [], 'rejected' => [], 'expired' => [], 'cancelled' => []];

    private function nextNumber(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "finance.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'finance', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    /** Coverage split: insurer = min(total×coverage%, per_visit_limit) − copay; patient = remainder. bc math. */
    public function coverageSplit(PatientInsuranceMembership $m, float $total): array
    {
        abort_unless($m->status === 'active', 422, 'Membership not active.');
        $plan = $m->plan;
        $insurer = bcmul((string) $total, bcdiv($plan->coverage_percent, '100', 4), 2);
        if ($plan->per_visit_limit) { $insurer = bccomp($insurer, (string) $plan->per_visit_limit, 2) > 0 ? (string) $plan->per_visit_limit : $insurer; }
        if ($plan->copay_fixed) { $insurer = bcsub($insurer, (string) $plan->copay_fixed, 2); }
        $insurer = max(0, (float) $insurer);
        return ['insurer' => $insurer, 'patient' => round($total - $insurer, 2), 'coverage_percent' => (float) $plan->coverage_percent];
    }

    public function verifyMembership(PatientInsuranceMembership $m, int $userId): void
    {
        abort_unless($m->status === 'pending', 422);
        $expired = $m->effective_to && $m->effective_to->isPast();
        $m->update(['status' => $expired ? 'expired' : 'active', 'verified_by' => $userId, 'verified_at' => now()]);
        Audit::record('finance.membership_verified', $m->patient, null, ['member' => $m->member_number]);
    }

    /** Claim from an issued invoice + active membership — unique(invoice,membership) prevents dup. */
    public function createClaim(PatientInsuranceMembership $m, int $invoiceId, int $userId): InsuranceClaim
    {
        $inv = \Modules\Finance\Models\Invoice::findOrFail($invoiceId);
        abort_unless($inv->patient_id === $m->patient_id && in_array($inv->status, ['issued', 'overdue', 'partially_paid', 'paid']), 422);
        $split = $this->coverageSplit($m, (float) $inv->total);
        $c = InsuranceClaim::create([
            'claim_number' => $this->nextNumber('CLM', 'insurance_claims', 'claim_number'),
            'invoice_id' => $inv->id, 'patient_insurance_membership_id' => $m->id,
            'patient_id' => $m->patient_id, 'branch_id' => $inv->branch_id,
            'amount' => $inv->total, 'insurer_share' => $split['insurer'], 'patient_share' => $split['patient'],
            'status' => 'draft', 'created_by' => $userId,
        ]);
        Audit::record('finance.claim_created', $m->patient, null, ['claim' => $c->claim_number], $inv->branch_id);
        return $c;
    }

    public function transitionClaim(InsuranceClaim $c, string $to, int $userId, array $meta = []): InsuranceClaim
    {
        abort_unless(in_array($to, static::CLAIM_TRANSITIONS[$c->status] ?? []), 422, "Cannot move {$c->status}→{$to}.");
        abort_if(in_array($to, ['rejected', 'queried']) && ! ($meta['reason'] ?? null), 422, 'Reason required.');
        $c->update(['status' => $to] + match ($to) {
            'submitted' => ['submitted_at' => now(), 'submit_method' => $meta['method'] ?? 'manual'],
            'approved', 'partially_approved' => ['approved_amount' => $meta['amount'] ?? $c->insurer_share],
            'rejected' => ['reject_reason' => $meta['reason'] ?? null],
            'paid', 'partially_paid' => ['paid_amount' => $meta['amount'] ?? $c->approved_amount ?? $c->insurer_share],
            default => [],
        });
        Audit::record("finance.claim_{$to}", $c->patient, null, ['claim' => $c->claim_number], $c->branch_id);
        return $c->fresh();
    }

    public function createPreauth(array $data, int $userId): InsurancePreauthorization
    {
        $m = PatientInsuranceMembership::findOrFail($data['patient_insurance_membership_id']);
        abort_unless($m->status === 'active', 422);
        abort_unless($m->plan->requires_preauth, 422, 'Plan does not require preauth.');
        return InsurancePreauthorization::create($data + ['auth_number' => $this->nextNumber('PA', 'insurance_preauthorizations', 'auth_number'), 'status' => 'draft', 'patient_id' => $m->patient_id, 'created_by' => $userId]);
    }

    public function transitionPreauth(InsurancePreauthorization $pa, string $to, int $userId, array $meta = []): InsurancePreauthorization
    {
        abort_unless(in_array($to, static::PA_TRANSITIONS[$pa->status] ?? []), 422);
        $pa->update(['status' => $to] + match ($to) {
            'approved', 'partially_approved' => ['approved_amount' => $meta['amount'] ?? $pa->estimated_cost, 'approved_sessions' => $meta['sessions'] ?? null, 'provider_ref' => $meta['provider_ref'] ?? null],
            default => [],
        });
        Audit::record("finance.preauth_{$to}", $pa->patient, null, ['pa' => $pa->auth_number]);
        return $pa->fresh();
    }

    public function dashboard(int $branchId): array
    {
        $claims = InsuranceClaim::where('branch_id', $branchId);
        return [
            'memberships' => PatientInsuranceMembership::where('status', 'active')->count(),
            'claims_pending' => (clone $claims)->whereIn('status', ['draft', 'submitted', 'acknowledged', 'under_review'])->count(),
            'claims_rejected' => (clone $claims)->where('status', 'rejected')->count(),
            'claims_paid' => (clone $claims)->where('status', 'paid')->count(),
            'insurer_receivable' => (float) (clone $claims)->whereIn('status', ['submitted', 'acknowledged', 'under_review', 'approved', 'partially_approved'])->sum('insurer_share'),
        ];
    }
}
