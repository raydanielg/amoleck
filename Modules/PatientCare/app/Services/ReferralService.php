<?php

namespace Modules\PatientCare\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Models\PatientReferral;

class ReferralService
{
    public const STATUSES = ['draft', 'submitted', 'received', 'under_review', 'accepted', 'rejected', 'completed', 'cancelled'];

    public const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['received', 'under_review', 'accepted', 'rejected', 'cancelled'],
        'received' => ['under_review', 'accepted', 'rejected', 'cancelled'],
        'under_review' => ['accepted', 'rejected', 'cancelled'],
        'accepted' => ['completed', 'cancelled'],
        'rejected' => [],
        'completed' => [],
        'cancelled' => [],
    ];

    public const PERMS = [
        'submitted' => 'patientcare.referrals.create',
        'received' => 'patientcare.referrals.review',
        'under_review' => 'patientcare.referrals.review',
        'accepted' => 'patientcare.referrals.accept',
        'rejected' => 'patientcare.referrals.reject',
        'completed' => 'patientcare.referrals.accept',
        'cancelled' => 'patientcare.referrals.cancel',
    ];

    public function nextNumber(): string
    {
        $prefix = \Modules\Foundation\Models\Setting::get('patientcare.referral_prefix', 'REF');

        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', 'patientcare.referral_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = PatientReferral::where('ref_number', 'like', "{$prefix}-".date('Y')."-%")->orderByDesc('ref_number')->value('ref_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) {
                $seq = $existing + 1;
            }
            DB::table('settings')->updateOrInsert(
                ['key' => 'patientcare.referral_sequence'],
                ['group' => 'patientcare', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]
            );

            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    public function create(array $data, int $userId): PatientReferral
    {
        return DB::transaction(function () use ($data, $userId) {
            $ref = PatientReferral::create($data + ['ref_number' => $this->nextNumber(), 'registered_by' => $userId, 'status' => 'submitted']);
            $ref->statusHistories()->create(['from_status' => null, 'to_status' => $ref->status, 'changed_by' => $userId]);
            Audit::record('referral.created', $ref->patient, null, ['ref_number' => $ref->ref_number, 'type' => $ref->type], $ref->receiving_branch_id);

            return $ref;
        });
    }

    public function transition(PatientReferral $ref, string $to, ?string $reason, int $userId): PatientReferral
    {
        abort_unless(in_array($to, static::TRANSITIONS[$ref->status] ?? []), 422, "Cannot move referral from {$ref->status} to {$to}.");
        abort_if($to === 'rejected' && ! $reason, 422, 'Rejection reason is required.');

        $from = $ref->status;
        DB::transaction(function () use ($ref, $to, $from, $reason, $userId) {
            $ref->update(['status' => $to]);
            $ref->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
        });
        Audit::record("referral.{$to}", $ref->patient, ['status' => $from], ['status' => $to, 'reason' => $reason], $ref->receiving_branch_id);

        return $ref->fresh();
    }

    public function statusHistories(PatientReferral $ref)
    {
        return $ref->hasMany(\Modules\PatientCare\Models\PatientReferralStatusHistory::class, 'patient_referral_id');
    }
}
