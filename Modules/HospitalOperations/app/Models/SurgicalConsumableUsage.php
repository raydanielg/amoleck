<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_operation_id','surgical_case_id','patient_id','inventory_item_id','inventory_batch_id','warehouse_id','qty_used','qty_returned','qty_wasted','stock_issue_id','recorded_by'])]
class SurgicalConsumableUsage extends Model
{
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function item(): BelongsTo { return $this->belongsTo(\Modules\Inventory\Models\InventoryItem::class,'inventory_item_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(\Modules\Inventory\Models\InventoryBatch::class,'inventory_batch_id'); }
}
