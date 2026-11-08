<?php
namespace Modules\HospitalOperations\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['encounter_number','emergency_visit_id','clinician_id','status','emergency_encounter_id','complaint','history','examination','assessed_by','working_diagnosis','differential','diagnosed_by','plan','version','created_by','approved_by','procedure_type','notes','performed_by','completed_at','referral_number','type','destination','reason','referred_by'])]
class EmergencyTreatmentPlan extends Model
{
    protected $table = 'emergency_treatment_plans';
    protected $casts = ['completed_at' => 'datetime'];
    public function encounter(): BelongsTo { return $this->belongsTo(EmergencyEncounter::class, 'emergency_encounter_id'); }
    public function visit(): BelongsTo { return $this->belongsTo(EmergencyVisit::class, 'emergency_visit_id'); }
}
