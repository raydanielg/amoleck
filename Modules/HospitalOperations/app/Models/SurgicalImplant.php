<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surgical_operation_id','surgical_case_id','patient_id','inventory_item_id','item_name','batch_number','serial_number','expiry_date','manufacturer','supplier','quantity','recorded_by'])]
class SurgicalImplant extends Model
{
    protected $casts = ['expiry_date' => 'date'];
    public function operation(): BelongsTo { return $this->belongsTo(SurgicalOperation::class); }
    public function item(): BelongsTo { return $this->belongsTo(\Modules\Inventory\Models\InventoryItem::class,'inventory_item_id'); }
}
