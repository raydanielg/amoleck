<?php

namespace Modules\HospitalOperations\Services;

use Modules\Administration\Models\Employee;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Services\FinanceService;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\Bed;
use Modules\HospitalOperations\Models\SurgicalBloodRequirement;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\Ward;
use Modules\PatientCare\Models\PatientSurgicalHistory;

/**
 * Integration boundary for Finance, Admissions/ICU, Blood Bank (Phase 12),
 * and patient surgical history. No duplicate subsystem is created here.
 */
class SurgicalIntegrationService
{
    /**
     * Capture a billable surgical event via canonical charge capture.
     * Idempotent: patient_charges has unique(source, source_id).
     */
    public function captureCharge(SurgicalCase $c, string $chargeType, int $clinicalServiceId, int $sourceId, int $userId, ?string $description = null): ?PatientCharge
    {
        $source = "surgery_{$chargeType}";
        if (PatientCharge::where('source', $source)->where('source_id', $sourceId)->exists()) {
            return PatientCharge::where('source', $source)->where('source_id', $sourceId)->first();
        }
        $charge = app(FinanceService::class)->createCharge([
            'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id, 'department_id' => $c->department_id,
            'clinical_service_id' => $clinicalServiceId, 'source' => $source, 'source_id' => $sourceId,
            'service_date' => now()->toDateTimeString(), 'quantity' => 1,
            'description' => $description ?? "Surgical {$chargeType} — case {$c->case_number}",
        ], $userId);
        Audit::record('surgery.charge_captured', $c->patient, null, ['charge' => $charge->charge_number, 'type' => $chargeType], $c->branch_id);
        return $charge;
    }

    /**
     * Record the completed operation in the canonical patient surgical history.
     */
    public function recordSurgicalHistory(SurgicalCase $c, int $userId): ?PatientSurgicalHistory
    {
        $op = $c->operations()->where('status', 'completed')->latest()->first();
        abort_unless($op, 422, 'No completed operation.');
        $exists = PatientSurgicalHistory::where('patient_id', $c->patient_id)
            ->where('description', 'like', "%{$c->case_number}%")->exists();
        if ($exists) {
            return null;
        }
        $h = PatientSurgicalHistory::create([
            'patient_id' => $c->patient_id,
            'procedure' => $c->procedure?->name ?? $op->procedures()->first()?->procedure_name ?? 'Surgical procedure',
            'procedure_at' => ($op->procedure_end_at ?? now())->toDateString(),
            'facility' => $c->branch?->name,
            'outcome' => $op->outcome,
            'complications' => $op->complications,
            'source' => 'hospital_record',
            'description' => "Case {$c->case_number} / Operation {$op->operation_number} / Surgeon: ".($op->surgeon?->name ?? 'n/a'),
            'recorded_by' => $userId,
        ]);
        Audit::record('surgery.history_recorded', $c->patient, null, ['case' => $c->case_number], $c->branch_id);
        return $h;
    }

    /**
     * Create a post-operative ward admission via canonical IPD service.
     * ICU/HDU transfers reuse wards/beds — a ward with category 'icu' acts as
     * the ICU destination until the dedicated Phase 11 module exists.
     */
    public function createPostopAdmission(SurgicalCase $c, int $wardId, int $bedId, int $userId): Admission
    {
        $bed = Bed::findOrFail($bedId);
        $ward = Ward::findOrFail($wardId);
        abort_unless((int) $bed->branch_id === (int) $c->branch_id && (int) $ward->branch_id === (int) $c->branch_id, 422, 'Destination in another branch.');
        abort_unless($bed->status === 'available', 422, 'Destination bed not available.');
        $ipd = app(IpdService::class);
        $a = $ipd->createAdmission([
            'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id, 'department_id' => $c->department_id,
            'clinician_id' => $c->surgeon_id, 'type' => 'transfer_in', 'source' => 'theatre',
            'priority' => $c->priority === 'emergency' ? 'urgent' : 'routine',
            'reason' => "Post-operative admission — case {$c->case_number}",
        ], $userId);
        $ipd->transition($a, 'approved', $userId, 'Post-operative transfer');
        $ipd->transition($a, 'awaiting_bed', $userId);
        $ipd->allocateBed($a, $bed, $userId);
        Audit::record('surgery.postop_admission', $c->patient, null, ['adm' => $a->admission_number, 'ward' => $ward->name], $c->branch_id);
        return $a;
    }

    /** ICU destination lookup — wards flagged as ICU in canonical bed infrastructure. */
    public function icuBeds(int $branchId)
    {
        return Bed::where('branch_id', $branchId)->where('status', 'available')
            ->whereHas('ward', fn ($q) => $q->whereIn('category', ['icu', 'hdu', 'high_dependency']))
            ->with('ward:id,name,category')->get(['id', 'code', 'ward_id', 'branch_id']);
    }

    // ---- Blood Bank boundary (Phase 12 dependency — no availability is invented) ----
    public function requestBloodRequirement(SurgicalCase $c, array $data, int $userId): SurgicalBloodRequirement
    {
        $r = $c->bloodRequirements()->create($data + ['branch_id' => $c->branch_id, 'status' => 'requested', 'requested_by' => $userId]);
        Audit::record('surgery.blood_requested', $c->patient, null, ['product' => $r->product_type, 'units' => $r->units], $c->branch_id);
        return $r;
    }

    /** Manual confirmation only — no automated availability. Real issuing requires Phase 12. */
    public function confirmBloodRequirement(SurgicalBloodRequirement $r, string $status, int $userId): SurgicalBloodRequirement
    {
        abort_unless($r->status === 'requested', 422);
        abort_unless(in_array($status, ['confirmed', 'unavailable', 'cancelled']), 422);
        $r->update(['status' => $status, 'confirmed_by' => $userId, 'confirmed_at' => now()]);
        Audit::record('surgery.blood_confirmed', $r->case->patient, ['status' => 'requested'], ['status' => $status], $r->branch_id);
        return $r->fresh();
    }

    /** Surgeon eligibility: active user + (optional) active employee at branch. */
    public function eligibleStaff(int $branchId, ?string $roleHint = null)
    {
        return Employee::where('primary_branch_id', $branchId)->where('status', 'active')
            ->whereNotNull('user_id')->with('user:id,name,job_title')
            ->get()->map(fn ($e) => ['id' => $e->user_id, 'name' => $e->user?->name ?? $e->full_name, 'job_title' => $e->job_title ?? $e->user?->job_title]);
    }
}
