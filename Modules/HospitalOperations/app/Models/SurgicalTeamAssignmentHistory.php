<?php

namespace Modules\HospitalOperations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['surgical_team_assignment_id','action','reason','changed_by'])]
class SurgicalTeamAssignmentHistory extends Model
{
    protected $table = 'surgical_team_assignment_histories';
}
