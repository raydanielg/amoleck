<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','generic_name','brand_name','category','dosage_form','strength','strength_unit','routes','controlled','active','order_number','patient_id','admission_id','branch_id','ward_id','medication_catalogue_id','dose','dose_unit','route','frequency','schedule_times','type','prn_min_interval_minutes','prn_max_frequency','instructions','indication','status','starts_at','ends_at','discontinued_at','discontinue_reason','created_by','approved_by','medication_order_id','version','snapshot','action','reason','acted_by','scheduled_at','medication_scheduled_dose_id','actual_dose','actual_route','administered_at','administered_by','verified_by','notes','patient_response'])]
class MedicationOrderVersion extends Model
{
    protected $casts = ['active' => 'boolean', 'controlled' => 'boolean', 'routes' => 'array', 'schedule_times' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'discontinued_at' => 'datetime', 'scheduled_at' => 'datetime', 'administered_at' => 'datetime', 'snapshot' => 'array'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function versions(): HasMany { return $this->hasMany(MedicationOrderVersion::class, 'medication_order_id'); }
    public function scheduledDoses(): HasMany { return $this->hasMany(MedicationScheduledDose::class, 'medication_order_id'); }
    public function administrations(): HasMany { return $this->hasMany(MedicationAdministration::class, 'medication_order_id'); }
}
