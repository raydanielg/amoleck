<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['operative_note_id','version','snapshot','action','acted_by'])]
class OperativeNoteVersion extends Model
{
    protected $casts = ['snapshot' => 'array'];
}
