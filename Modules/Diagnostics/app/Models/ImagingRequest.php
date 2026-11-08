<?php
namespace Modules\Diagnostics\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['request_number','patient_id','branch_id','diagnostic_test_id','modality','body_region','priority','clinical_indication','status','ordered_by','reviewed_by','reject_reason','imaging_request_id','equipment_id','scheduled_at','started_at','completed_at','notes','study_uid','imaging_procedure_id','pacs_reference','series_count','report_number','imaging_study_id','findings','impression','radiologist_id','authorized_by','authorized_at','version','supersedes_id'])]
class ImagingRequest extends Model
{
    protected $table = 'imaging_requests';
    protected $casts = ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'authorized_at' => 'datetime'];
    public function request(): BelongsTo { return $this->belongsTo(ImagingRequest::class, 'imaging_request_id'); }
    public function procedure(): BelongsTo { return $this->belongsTo(ImagingProcedure::class, 'imaging_procedure_id'); }
    public function study(): BelongsTo { return $this->belongsTo(ImagingStudy::class, 'imaging_study_id'); }
    public function test(): BelongsTo { return $this->belongsTo(DiagnosticTest::class, 'diagnostic_test_id'); }
    public function procedures(): HasMany { return $this->hasMany(ImagingProcedure::class, 'imaging_request_id'); }
    public function reports(): HasMany { return $this->hasMany(RadiologyReport::class, 'imaging_request_id'); }
    public function equipment(): BelongsTo { return $this->belongsTo(\Modules\Inventory\Models\Equipment::class, 'equipment_id'); }
}
