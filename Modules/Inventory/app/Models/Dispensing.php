<?php
namespace Modules\Inventory\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['code','name','branch_id','warehouse_id','active','dispense_number','pharmacy_location_id','medication_order_id','patient_id','employee_id','status','pharmacist_id','notes','dispensing_id','inventory_item_id','inventory_batch_id','ordered_qty','dispensed_qty','remaining_qty','return_number','qty','reason','disposition','inspected_by','created_by'])]
class Dispensing extends Model
{
    protected $casts = ['active' => 'boolean', 'ordered_qty' => 'decimal:3', 'dispensed_qty' => 'decimal:3', 'remaining_qty' => 'decimal:3', 'qty' => 'decimal:3'];
    public function location(): BelongsTo { return $this->belongsTo(PharmacyLocation::class, 'pharmacy_location_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id'); }
    public function lines(): HasMany { return $this->hasMany(DispensingLine::class, 'dispensing_id'); }
    public function dispensing(): BelongsTo { return $this->belongsTo(Dispensing::class, 'dispensing_id'); }
}
