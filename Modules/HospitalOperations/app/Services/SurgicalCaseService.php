<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\SurgicalAlert;
use Modules\HospitalOperations\Models\SurgicalCancellation;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalConsultation;
use Modules\HospitalOperations\Models\SurgicalPostponement;
use Modules\HospitalOperations\Models\SurgicalProcedure;

class SurgicalCaseService
{
    /** Controlled state machine. Emergency cases may shortcut review stages. */
    public const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['pending_clinical_review', 'pending_surgical_approval', 'approved', 'cancelled'],
        'pending_clinical_review' => ['pending_surgical_approval', 'approved', 'rejected', 'cancelled'],
        'pending_surgical_approval' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['awaiting_preoperative_assessment', 'awaiting_consent', 'ready_for_scheduling', 'postponed', 'cancelled'],
        'awaiting_preoperative_assessment' => ['awaiting_consent', 'ready_for_scheduling', 'postponed', 'cancelled'],
        'awaiting_consent' => ['ready_for_scheduling', 'postponed', 'cancelled'],
        'ready_for_scheduling' => ['scheduled', 'postponed', 'cancelled'],
        'scheduled' => ['preoperative_preparation', 'in_theatre', 'postponed', 'cancelled'],
        'preoperative_preparation' => ['ready_for_theatre', 'postponed', 'cancelled'],
        'ready_for_theatre' => ['in_theatre', 'postponed', 'cancelled'],
        'in_theatre' => ['procedure_completed'],
        'procedure_completed' => ['recovery'],
        'recovery' => ['postoperative_care'],
        'postoperative_care' => ['completed'],
        'rejected' => ['submitted'],
        'postponed' => ['ready_for_scheduling', 'cancelled'],
        'completed' => [], 'cancelled' => [],
    ];

    public function nextNumber(): string
    {
        return DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'surgery.case_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = SurgicalCase::where('case_number', 'like', 'SUR-'.date('Y').'-%')->orderByDesc('case_number')->value('case_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => 'surgery.case_sequence'], ['group' => 'surgery', 'value' => json_encode($seq), 'is_secret' => false, 'created_at' => now(), 'updated_at' => now()]);
            return sprintf('SUR-%s-%06d', date('Y'), $seq);
        });
    }

    public function create(array $data, int $userId): SurgicalCase
    {
        abort_unless(in_array($data['priority'] ?? 'elective', SurgicalCase::PRIORITIES), 422, 'Invalid priority.');
        if (! empty($data['surgical_procedure_id'])) {
            $proc = SurgicalProcedure::findOrFail($data['surgical_procedure_id']);
            abort_unless(app(SurgicalCatalogueService::class)->procedureAvailableIn($proc, (int) $data['branch_id']), 422, 'Procedure not available at this branch.');
            $data['procedure_version'] = $proc->version;
            $data['surgical_specialty_id'] = $data['surgical_specialty_id'] ?? $proc->surgical_specialty_id;
        }
        $c = SurgicalCase::create($data + ['case_number' => $this->nextNumber(), 'status' => 'draft', 'created_by' => $userId]);
        $c->statusHistories()->create(['to_status' => 'draft', 'changed_by' => $userId]);
        if (! empty($data['diagnosis'])) {
            $c->diagnoses()->create(['type' => 'working', 'description' => $data['diagnosis'], 'recorded_by' => $userId]);
        }
        Audit::record('surgery.case_created', $c->patient, null, ['case' => $c->case_number, 'priority' => $c->priority], $c->branch_id);
        $this->alert('case.submitted', $c, "New surgical case {$c->case_number} registered ({$c->priority}).");
        return $c;
    }

    /** Readiness blockers — a case must not be scheduled until these are satisfied. */
    public function readinessBlockers(SurgicalCase $c): array
    {
        $blockers = [];
        $preop = $c->preopAssessment;
        if (! $preop || ! in_array($preop->status, ['cleared', 'cleared_with_conditions'])) {
            $blockers[] = 'preoperative_assessment';
        }
        $proc = $c->procedure;
        if ($proc && $proc->anesthesia_options && ! empty($proc->anesthesia_options)) {
            $ap = $c->anesthesiaPreassessment;
            if (! $ap || $ap->status !== 'approved') {
                $blockers[] = 'anesthesia_preassessment';
            }
        }
        if (! $proc || $proc->consent_required) {
            $consent = $c->consents()->where('type', 'procedure')->where('status', 'consented')->exists();
            if (! $consent) {
                $blockers[] = 'procedure_consent';
            }
        }
        return $blockers;
    }

    public function transition(SurgicalCase $c, string $to, int $userId, ?string $reason = null, ?string $approvalRef = null): SurgicalCase
    {
        $from = $c->status;
        abort_unless(in_array($to, static::TRANSITIONS[$from] ?? []), 422, "Cannot move {$from}→{$to}.");
        abort_if(in_array($to, ['cancelled', 'rejected', 'postponed']) && ! $reason, 422, 'Reason required.');
        // Readiness gate: reaching ready_for_scheduling requires clinical gates (unless documented emergency)
        if ($to === 'ready_for_scheduling' && $c->priority !== 'emergency') {
            $blockers = $this->readinessBlockers($c);
            abort_if($blockers, 422, 'Readiness blockers: '.implode(', ', $blockers));
        }
        DB::transaction(function () use ($c, $from, $to, $userId, $reason, $approvalRef) {
            $c->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'approval_reference' => $approvalRef, 'changed_by' => $userId]);
            $c->update(['status' => $to] + ($to === 'approved' ? ['approved_by' => $userId, 'approved_at' => now()] : []));
        });
        Audit::record("surgery.case_{$to}", $c->patient, ['status' => $from], ['status' => $to], $c->branch_id);
        if (in_array($to, ['approved', 'scheduled', 'cancelled', 'postponed'])) {
            $this->alert("case.{$to}", $c, "Surgical case {$c->case_number} → {$to}.");
        }
        return $c->fresh();
    }

    public function saveConsultation(SurgicalCase $c, array $data, int $userId): SurgicalConsultation
    {
        abort_if(in_array($c->status, ['completed', 'cancelled', 'in_theatre', 'procedure_completed']), 422, 'Case closed.');
        return DB::transaction(function () use ($c, $data, $userId) {
            $con = $c->consultation;
            if ($con) {
                $con->versions()->create(['version' => $con->versions()->count() + 1, 'snapshot' => $con->toArray(), 'action' => 'updated', 'acted_by' => $userId]);
                $con->update($data + ['updated_by' => $userId]);
            } else {
                $con = $c->consultation()->create($data + ['clinician_id' => $userId, 'decision_at' => $data['decision_at'] ?? now()]);
                $con->versions()->create(['version' => 1, 'snapshot' => $con->toArray(), 'action' => 'created', 'acted_by' => $userId]);
            }
            Audit::record('surgery.consultation_saved', $c->patient, null, ['case' => $c->case_number, 'decision' => $con->decision], $c->branch_id);
            return $con->fresh();
        });
    }

    public function cancel(SurgicalCase $c, array $data, int $userId): SurgicalCancellation
    {
        return DB::transaction(function () use ($c, $data, $userId) {
            $rec = SurgicalCancellation::create([
                'surgical_case_id' => $c->id, 'surgical_booking_id' => $data['surgical_booking_id'] ?? null,
                'category' => $data['category'] ?? null, 'reason' => $data['reason'],
                'patient_notified' => (bool) ($data['patient_notified'] ?? false), 'notes' => $data['notes'] ?? null,
                'cancelled_by' => $userId, 'approved_by' => $data['approved_by'] ?? null,
            ]);
            $this->transition($c, 'cancelled', $userId, $data['reason']);
            return $rec;
        });
    }

    public function postpone(SurgicalCase $c, array $data, int $userId): SurgicalPostponement
    {
        return DB::transaction(function () use ($c, $data, $userId) {
            $rec = SurgicalPostponement::create([
                'surgical_case_id' => $c->id, 'surgical_booking_id' => $data['surgical_booking_id'] ?? null,
                'category' => $data['category'] ?? null, 'reason' => $data['reason'],
                'rescheduled_to' => $data['rescheduled_to'] ?? null,
                'patient_notified' => (bool) ($data['patient_notified'] ?? false), 'notes' => $data['notes'] ?? null,
                'postponed_by' => $userId, 'approved_by' => $data['approved_by'] ?? null,
            ]);
            $this->transition($c, 'postponed', $userId, $data['reason']);
            return $rec;
        });
    }

    public function alert(string $type, SurgicalCase $c, string $message): SurgicalAlert
    {
        return SurgicalAlert::create(['surgical_case_id' => $c->id, 'branch_id' => $c->branch_id, 'type' => $type, 'message' => $message, 'status' => 'recorded']);
    }
}
