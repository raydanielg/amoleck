<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operating_theatre_id','day_of_week','start_time','end_time','active'])]
class TheatreOperatingHour extends Model
{
    protected $casts = ['active' => 'boolean'];
    public function theatre(): BelongsTo { return $this->belongsTo(OperatingTheatre::class); }
}
