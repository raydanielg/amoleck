<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','branch_id','department_id','category','location','capacity','equipment_ids','status','prep_minutes','cleaning_minutes','notes'])]
class OperatingTheatre extends Model
{
    public const STATUSES = ['available','reserved','occupied','cleaning','maintenance','inspection_required','out_of_service','inactive'];
    public const BOOKABLE = ['available'];
    protected $casts = ['equipment_ids' => 'array'];
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function hours(): HasMany { return $this->hasMany(TheatreOperatingHour::class); }
    public function exceptions(): HasMany { return $this->hasMany(TheatreScheduleException::class); }
    public function bookings(): HasMany { return $this->hasMany(SurgicalBooking::class); }
    public function statusHistories(): HasMany { return $this->hasMany(OperatingTheatreStatusHistory::class); }
}
