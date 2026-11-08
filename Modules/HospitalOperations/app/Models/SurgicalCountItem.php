<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_count_id','item_name','expected_qty','counted_qty','difference'])]
class SurgicalCountItem extends Model
{
}
