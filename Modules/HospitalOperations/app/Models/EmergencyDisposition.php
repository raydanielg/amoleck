<?php
namespace Modules\HospitalOperations\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['emergency_visit_id','type','clinical_summary','status','proposed_by','approved_by','discharge_number','summary','discharged_by','authorized_by','transfer_number','destination','requested_by','received_by','received_at'])]
class EmergencyDisposition extends Model
{
    protected $table = 'emergency_dispositions';
    protected $casts = ['received_at' => 'datetime'];
    public function visit(): BelongsTo { return $this->belongsTo(EmergencyVisit::class, 'emergency_visit_id'); }
}
