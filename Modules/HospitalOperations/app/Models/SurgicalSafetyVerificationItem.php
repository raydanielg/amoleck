<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_safety_verification_id','item_key','label','checked','value','checked_by','notes'])]
class SurgicalSafetyVerificationItem extends Model
{
    protected $casts = ['checked' => 'boolean'];
}
