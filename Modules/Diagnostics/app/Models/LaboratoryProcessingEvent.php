<?php
namespace Modules\Diagnostics\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['laboratory_specimen_id','event','equipment_id','performed_by','notes','aliquot_number','volume','status','created_by','result_number','laboratory_order_item_id','entered_by','verified_by','comments','laboratory_test_result_id','diagnostic_test_parameter_id','value','flag','diagnostic_reference_range_id','laboratory_result_value_id','acknowledged_by','acknowledged_at','diagnostic_test_id','result'])]
class LaboratoryProcessingEvent extends Model
{
    protected $table = 'laboratory_processing_events';
    protected $casts = ['volume' => 'decimal:2', 'acknowledged_at' => 'datetime'];
    public function specimen(): BelongsTo { return $this->belongsTo(LaboratorySpecimen::class, 'laboratory_specimen_id'); }
    public function item(): BelongsTo { return $this->belongsTo(LaboratoryOrderItem::class, 'laboratory_order_item_id'); }
    public function values(): HasMany { return $this->hasMany(LaboratoryResultValue::class, 'laboratory_test_result_id'); }
    public function parameter(): BelongsTo { return $this->belongsTo(DiagnosticTestParameter::class, 'diagnostic_test_parameter_id'); }
    public function range(): BelongsTo { return $this->belongsTo(DiagnosticReferenceRange::class, 'diagnostic_reference_range_id'); }
    public function result(): BelongsTo { return $this->belongsTo(LaboratoryTestResult::class, 'laboratory_test_result_id'); }
}
