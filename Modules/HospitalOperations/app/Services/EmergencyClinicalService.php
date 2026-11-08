<?php
namespace Modules\HospitalOperations\Services;
use Illuminate\Support\Facades\DB;
use Modules\HospitalOperations\Models\{EmergencyAssessment, EmergencyDiagnosis, EmergencyEncounter, EmergencyIntervention, EmergencyReferral, EmergencyTreatmentPlan, EmergencyVisit};
use Modules\Foundation\Support\Audit;

class EmergencyClinicalService
{
    private function num(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "er.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'er', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    public function openEncounter(EmergencyVisit $v, int $userId): EmergencyEncounter
    {
        abort_unless($v->status === 'triaged', 422, 'Triage first.');
        return EmergencyEncounter::firstOrCreate(
            ['emergency_visit_id' => $v->id],
            ['encounter_number' => $this->num('ENC', 'emergency_encounters', 'encounter_number'), 'clinician_id' => $userId, 'status' => 'awaiting_clinician']
        );
    }

    public function assess(EmergencyEncounter $e, array $data, int $userId): EmergencyAssessment
    {
        return $e->assessments()->create($data + ['status' => 'draft', 'assessed_by' => $userId]);
    }

    public function diagnose(EmergencyEncounter $e, array $data, int $userId): EmergencyDiagnosis
    {
        return $e->diagnoses()->create($data + ['status' => 'working', 'diagnosed_by' => $userId]);
    }

    public function createPlan(EmergencyEncounter $e, string $plan, int $userId): EmergencyTreatmentPlan
    {
        return $e->treatmentPlans()->create(['plan' => $plan, 'status' => 'draft', 'version' => 1, 'created_by' => $userId]);
    }

    public function approvePlan(EmergencyTreatmentPlan $p, int $userId): EmergencyTreatmentPlan
    {
        abort_unless($p->status === 'draft', 422);
        abort_if($p->created_by === $userId, 422, 'Cannot approve own plan.');
        $p->update(['status' => 'approved', 'approved_by' => $userId]);
        Audit::record('er.plan_approved', null, null, ['plan' => $p->id]);
        return $p->fresh();
    }

    public function performIntervention(EmergencyEncounter $e, string $type, ?string $notes, int $userId): EmergencyIntervention
    {
        return $e->interventions()->create(['procedure_type' => $type, 'notes' => $notes, 'status' => 'planned', 'performed_by' => $userId]);
    }

    public function completeIntervention(EmergencyIntervention $i, int $userId): EmergencyIntervention
    {
        abort_unless($i->status === 'planned', 422);
        $i->update(['status' => 'completed', 'completed_at' => now()]);
        Audit::record('er.intervention_completed', null, null, ['int' => $i->id]);
        return $i->fresh();
    }

    public function refer(EmergencyEncounter $e, string $type, string $destination, string $reason, int $userId): EmergencyReferral
    {
        $r = $e->referrals()->create(['referral_number' => $this->num('REF', 'emergency_referrals', 'referral_number'), 'type' => $type, 'destination' => $destination, 'reason' => $reason, 'status' => 'pending', 'referred_by' => $userId]);
        Audit::record('er.referred', null, null, ['ref' => $r->referral_number]);
        return $r;
    }
}
