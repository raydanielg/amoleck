<?php
namespace Modules\Diagnostics\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['report_number','laboratory_test_result_id','version','supersedes_id','status','released_by','released_at','diagnostic_report_id','reviewed_by','comments','follow_up'])]
class DiagnosticReport extends Model
{
    protected $table = 'diagnostic_reports';
    protected $casts = ['released_at' => 'datetime'];
    public function result(): BelongsTo { return $this->belongsTo(LaboratoryTestResult::class, 'laboratory_test_result_id'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(DiagnosticReport::class, 'supersedes_id'); }
    public function report(): BelongsTo { return $this->belongsTo(DiagnosticReport::class, 'diagnostic_report_id'); }
}
