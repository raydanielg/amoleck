<?php
namespace Modules\HospitalOperations\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['code','label','priority','target_minutes','active','visit_number','patient_id','temp_patient_number','branch_id','arrival_mode','presenting_complaint','status','registered_by','emergency_visit_id','emergency_acuity_category_id','complaint','notes','assessed_by','type','raised_by','acknowledged_by','acknowledged_at','from_user_id','to_user_id'])]
class EmergencyAlert extends Model
{
    protected $table = 'emergency_alerts';
    protected $casts = ['active' => 'boolean', 'acknowledged_at' => 'datetime'];
    public function visit(): BelongsTo { return $this->belongsTo(EmergencyVisit::class, 'emergency_visit_id'); }
    public function acuity(): BelongsTo { return $this->belongsTo(EmergencyAcuityCategory::class, 'emergency_acuity_category_id'); }
    public function assessments(): HasMany { return $this->hasMany(EmergencyTriageAssessment::class, 'emergency_visit_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
}
