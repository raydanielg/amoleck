<?php
namespace Modules\Inventory\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['code','name','category','email','phone','status','requisition_number','inventory_item_id','warehouse_id','branch_id','qty','justification','requested_by','approved_by','po_number','supplier_id','stock_requisition_id','unit_price','received_qty','created_by','available','outgoing_reserved','suggested_qty'])]
class StockRequisition extends Model
{
    protected $casts = ['qty' => 'decimal:3', 'unit_price' => 'decimal:2', 'received_qty' => 'decimal:3', 'available' => 'decimal:3', 'outgoing_reserved' => 'decimal:3', 'suggested_qty' => 'decimal:3'];
    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function requisition(): BelongsTo { return $this->belongsTo(PurchaseRequisition::class, 'stock_requisition_id'); }
}
