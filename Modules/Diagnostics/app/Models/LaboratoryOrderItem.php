<?php
namespace Modules\Diagnostics\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['order_number','patient_id','branch_id','ordered_by','priority','status','clinical_indication','reject_reason','reviewed_by','laboratory_order_id','diagnostic_test_id','test_version','specimen_number','laboratory_order_item_id','diagnostic_specimen_id','collected_by','collected_at','recalled_from_id','accession_number','laboratory_specimen_id','received_by'])]
class LaboratoryOrderItem extends Model
{
    protected $table = 'laboratory_order_items';
    protected $casts = ['collected_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(LaboratoryOrder::class, 'laboratory_order_id'); }
    public function items(): HasMany { return $this->hasMany(LaboratoryOrderItem::class, 'laboratory_order_id'); }
    public function test(): BelongsTo { return $this->belongsTo(DiagnosticTest::class, 'diagnostic_test_id'); }
    public function specimens(): HasMany { return $this->hasMany(LaboratorySpecimen::class, 'laboratory_order_item_id'); }
    public function item(): BelongsTo { return $this->belongsTo(LaboratoryOrderItem::class, 'laboratory_order_item_id'); }
    public function specimenType(): BelongsTo { return $this->belongsTo(DiagnosticSpecimen::class, 'diagnostic_specimen_id'); }
    public function accession(): HasMany { return $this->hasMany(LaboratoryAccession::class, 'laboratory_specimen_id'); }
}
