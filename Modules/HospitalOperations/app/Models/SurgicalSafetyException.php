<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_case_id','stage','item_key','reason','authorized_by','created_by'])]
class SurgicalSafetyException extends Model
{
}
