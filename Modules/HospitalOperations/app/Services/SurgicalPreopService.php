<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\AnesthesiaPreassessment;
use Modules\HospitalOperations\Models\PreoperativeAssessment;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalChecklistItem;
use Modules\HospitalOperations\Models\SurgicalChecklistTemplate;
use Modules\HospitalOperations\Models\SurgicalConsent;

class SurgicalPreopService
{
    public const ASSESSMENT_STATUSES = ['in_progress', 'pending_review', 'cleared', 'cleared_with_conditions', 'not_cleared', 'requires_further_assessment'];
    public const ANESTHESIA_STATUSES = ['in_progress', 'pending_review', 'approved', 'not_approved', 'requires_further_assessment'];
    public const CONSENT_TYPES = ['procedure', 'anesthesia', 'blood_product', 'additional', 'other'];
    public const CONSENT_STATUSES = ['pending', 'consented', 'declined', 'withdrawn', 'requires_reconfirmation', 'not_applicable'];
    public const ITEM_STATUSES = ['pending', 'completed', 'not_applicable', 'blocked', 'requires_review'];

    // ---- Pre-operative assessment ----
    public function saveAssessment(SurgicalCase $c, array $data, int $userId): PreoperativeAssessment
    {
        abort_if(in_array($c->status, ['completed', 'cancelled', 'in_theatre', 'procedure_completed', 'recovery']), 422, 'Case closed for assessment.');
        return DB::transaction(function () use ($c, $data, $userId) {
            $a = $c->preopAssessment;
            if ($a) {
                abort_if(in_array($a->status, ['cleared', 'cleared_with_conditions', 'not_cleared']) && ($data['status'] ?? $a->status) === $a->status && empty($data), 422);
                $a->versions()->create(['version' => $a->version, 'snapshot' => $a->toArray(), 'action' => 'amended', 'acted_by' => $userId]);
                $a->update($data + ['version' => $a->version + 1]);
            } else {
                $a = $c->preopAssessment()->create($data + [
                    'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id,
                    'clinician_id' => $userId, 'status' => $data['status'] ?? 'in_progress', 'assessed_at' => now(),
                ]);
                $a->versions()->create(['version' => 1, 'snapshot' => $a->toArray(), 'action' => 'created', 'acted_by' => $userId]);
            }
            Audit::record('surgery.preop_saved', $c->patient, null, ['case' => $c->case_number, 'status' => $a->status], $c->branch_id);
            return $a->fresh();
        });
    }

    /** Clinical clearance — only an authorized clinician may do this (permission enforced at controller). */
    public function reviewAssessment(PreoperativeAssessment $a, string $status, int $userId, ?string $notes = null): PreoperativeAssessment
    {
        abort_unless(in_array($status, ['cleared', 'cleared_with_conditions', 'not_cleared', 'requires_further_assessment']), 422, 'Invalid review outcome.');
        abort_if($a->clinician_id === $userId && in_array($status, ['cleared', 'cleared_with_conditions']), 422, 'Cannot clear own assessment.');
        return DB::transaction(function () use ($a, $status, $userId, $notes) {
            $a->versions()->create(['version' => $a->version, 'snapshot' => $a->toArray(), 'action' => 'reviewed', 'acted_by' => $userId]);
            $a->update(['status' => $status, 'reviewed_by' => $userId, 'version' => $a->version + 1,
                'cleared_at' => in_array($status, ['cleared', 'cleared_with_conditions']) ? now() : null,
                'clinical_notes' => $notes ? trim(($a->clinical_notes ?? '')."\n[review] ".$notes) : $a->clinical_notes]);
            if (in_array($status, ['cleared', 'cleared_with_conditions']) && $a->case->status === 'awaiting_preoperative_assessment') {
                $a->case->statusHistories()->create(['from_status' => 'awaiting_preoperative_assessment', 'to_status' => 'awaiting_consent', 'reason' => 'Pre-operative assessment cleared', 'changed_by' => $userId]);
                $a->case->update(['status' => 'awaiting_consent']);
            }
            Audit::record('surgery.preop_reviewed', $a->patient, null, ['status' => $status], $a->branch_id);
            return $a->fresh();
        });
    }

    // ---- Anesthesia pre-assessment ----
    public function saveAnesthesiaAssessment(SurgicalCase $c, array $data, int $userId): AnesthesiaPreassessment
    {
        abort_if(in_array($c->status, ['completed', 'cancelled']), 422, 'Case closed.');
        return DB::transaction(function () use ($c, $data, $userId) {
            $a = $c->anesthesiaPreassessment;
            if ($a) {
                abort_if($a->status === 'approved', 422, 'Approved assessment is locked; create amendment via review.');
                $a->versions()->create(['version' => $a->version, 'snapshot' => $a->toArray(), 'action' => 'amended', 'acted_by' => $userId]);
                $a->update($data + ['version' => $a->version + 1]);
            } else {
                $a = $c->anesthesiaPreassessment()->create($data + [
                    'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id,
                    'anesthetist_id' => $userId, 'status' => $data['status'] ?? 'in_progress',
                ]);
                $a->versions()->create(['version' => 1, 'snapshot' => $a->toArray(), 'action' => 'created', 'acted_by' => $userId]);
            }
            Audit::record('surgery.anesthesia_preassessment_saved', $c->patient, null, ['case' => $c->case_number], $c->branch_id);
            return $a->fresh();
        });
    }

    public function reviewAnesthesiaAssessment(AnesthesiaPreassessment $a, string $status, int $userId): AnesthesiaPreassessment
    {
        abort_unless(in_array($status, ['approved', 'not_approved', 'requires_further_assessment']), 422);
        return DB::transaction(function () use ($a, $status, $userId) {
            $a->versions()->create(['version' => $a->version, 'snapshot' => $a->toArray(), 'action' => 'reviewed', 'acted_by' => $userId]);
            $a->update(['status' => $status, 'version' => $a->version + 1] + ($status === 'approved' ? ['approved_by' => $userId, 'approved_at' => now()] : []));
            Audit::record('surgery.anesthesia_preassessment_reviewed', $a->patient, null, ['status' => $status], $a->branch_id);
            return $a->fresh();
        });
    }

    // ---- Consents ----
    public function recordConsent(SurgicalCase $c, array $data, int $userId): SurgicalConsent
    {
        abort_unless(in_array($data['type'], self::CONSENT_TYPES), 422, 'Invalid consent type.');
        abort_unless(in_array($data['status'], self::CONSENT_STATUSES), 422, 'Invalid status.');
        abort_if($data['status'] === 'consented' && empty($data['decision']), 422, 'Decision required.');
        return DB::transaction(function () use ($c, $data, $userId) {
            $con = $c->consents()->where('type', $data['type'])->first();
            if ($con) {
                abort_if($con->status === 'withdrawn' && ($data['status'] ?? '') !== 'consented', 422, 'Withdrawn consent requires fresh consent record.');
                $con->versions()->create(['version' => $con->version, 'snapshot' => $con->toArray(), 'action' => 'amended', 'acted_by' => $userId]);
                $con->update($data + ['version' => $con->version + 1, 'consented_at' => $data['status'] === 'consented' ? now() : $con->consented_at]);
            } else {
                $con = $c->consents()->create($data + [
                    'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id,
                    'surgical_procedure_id' => $c->surgical_procedure_id,
                    'consented_at' => ($data['status'] ?? null) === 'consented' ? now() : null,
                    'recorded_by' => $userId,
                ]);
                $con->versions()->create(['version' => 1, 'snapshot' => $con->toArray(), 'action' => 'created', 'acted_by' => $userId]);
            }
            Audit::record('surgery.consent_recorded', $c->patient, null, ['case' => $c->case_number, 'type' => $con->type, 'status' => $con->status], $c->branch_id);
            return $con->fresh();
        });
    }

    public function withdrawConsent(SurgicalConsent $con, string $reason, int $userId): SurgicalConsent
    {
        abort_unless($con->status === 'consented', 422, 'Only consented records can be withdrawn.');
        abort_if(in_array($con->case->status, ['completed', 'cancelled']), 422, 'Case closed.');
        return DB::transaction(function () use ($con, $reason, $userId) {
            $con->versions()->create(['version' => $con->version, 'snapshot' => $con->toArray(), 'action' => 'withdrawn', 'acted_by' => $userId]);
            $con->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdrawn_by' => $userId, 'withdrawal_reason' => $reason, 'version' => $con->version + 1]);
            Audit::record('surgery.consent_withdrawn', $con->patient, ['status' => 'consented'], ['status' => 'withdrawn'], $con->branch_id);
            app(SurgicalCaseService::class)->alert('consent.withdrawn', $con->case, "Consent {$con->type} withdrawn for case {$con->case->case_number}.");
            return $con->fresh();
        });
    }

    // ---- Preparation checklist ----
    /** Instantiate checklist items from the active template for a stage. */
    public function instantiateChecklist(SurgicalCase $c, string $stage, int $userId): int
    {
        $tpl = SurgicalChecklistTemplate::where('stage', $stage)->where('active', true)->latest('version')->first();
        abort_unless($tpl, 422, "No active checklist template for stage {$stage}.");
        $created = 0;
        foreach ($tpl->items ?? [] as $item) {
            $created += (int) SurgicalChecklistItem::firstOrCreate([
                'surgical_case_id' => $c->id, 'surgical_checklist_template_id' => $tpl->id, 'item_key' => $item['key'],
            ], [
                'stage' => $stage, 'label' => $item['label'] ?? $item['key'],
                'mandatory' => (bool) ($item['mandatory'] ?? false), 'required_role' => $item['role'] ?? null,
            ])->wasRecentlyCreated;
        }
        Audit::record('surgery.checklist_instantiated', $c->patient, null, ['stage' => $stage, 'items' => $created], $c->branch_id);
        return $created;
    }

    public function completeItem(SurgicalChecklistItem $item, array $data, int $userId, array $userRoles): SurgicalChecklistItem
    {
        abort_unless(in_array($data['status'], self::ITEM_STATUSES), 422, 'Invalid item status.');
        abort_if($item->case->status === 'completed' || $item->case->status === 'cancelled', 422, 'Case closed.');
        // Role-gated clinical safety items cannot be completed by unauthorized staff
        if ($item->required_role && $item->mandatory) {
            abort_unless(in_array($item->required_role, $userRoles) || in_array('Super Admin', $userRoles), 403, 'Item requires role: '.$item->required_role);
        }
        $item->update(['status' => $data['status'], 'evidence' => $data['evidence'] ?? $item->evidence, 'notes' => $data['notes'] ?? $item->notes,
            'completed_by' => $userId, 'completed_at' => now()]);
        Audit::record('surgery.checklist_item', $item->case->patient, null, ['item' => $item->item_key, 'status' => $data['status']], $item->case->branch_id);
        return $item->fresh();
    }

    public function checklistBlockers(SurgicalCase $c, string $stage): array
    {
        return $c->checklistItems()->where('stage', $stage)->where('mandatory', true)
            ->whereNotIn('status', ['completed', 'not_applicable'])->pluck('item_key')->all();
    }
}
