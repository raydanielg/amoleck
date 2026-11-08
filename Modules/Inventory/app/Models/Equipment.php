<?php
namespace Modules\Inventory\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
#[Fillable(['code','name','parent_id','requires_calibration','active','equipment_number','equipment_category_id','serial_number','model','branch_id','department_id','finance_asset_id','status','condition','warranty_expiry','acquired_date','equipment_id','from_branch_id','to_branch_id','requested_by','approved_by','wo_number','type','scheduled_date','findings','assigned_to','created_by','calibrated_at','next_due','result','performed_by','started_at','ended_at','reason'])]
class Equipment extends Model
{
    protected $casts = ['requires_calibration' => 'boolean', 'active' => 'boolean', 'warranty_expiry' => 'date', 'acquired_date' => 'date', 'scheduled_date' => 'date', 'calibrated_at' => 'date', 'next_due' => 'date', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    public function category(): BelongsTo { return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function equipment(): BelongsTo { return $this->belongsTo(Equipment::class, 'equipment_id'); }
    public function downtime() { return $this->hasMany(EquipmentDowntime::class, 'equipment_id'); }
}
