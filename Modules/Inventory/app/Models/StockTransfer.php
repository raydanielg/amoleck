<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','type','parent_id','active','branch_id','manager_id','inventory_category_id','uom','tracking','reorder_level','reorder_qty','inventory_item_id','warehouse_id','batch_number','expiry_date','qty','reserved','unit_cost','status','receipt_number','source','received_by','issue_number','inventory_batch_id','issued_by','transfer_number','from_warehouse_id','to_warehouse_id','created_by','received_by','balance','source_type','source_id','system_qty','counted_qty','difference','reason','approved_by'])]
class StockTransfer extends Model
{
    protected $casts = ['active' => 'boolean', 'expiry_date' => 'date', 'qty' => 'decimal:3', 'reserved' => 'decimal:3', 'unit_cost' => 'decimal:2', 'balance' => 'decimal:3', 'system_qty' => 'decimal:3', 'counted_qty' => 'decimal:3', 'difference' => 'decimal:3'];
    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function batch(): BelongsTo { return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id'); }
    public function category(): BelongsTo { return $this->belongsTo(InventoryCategory::class, 'inventory_category_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
}
