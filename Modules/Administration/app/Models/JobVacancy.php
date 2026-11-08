<?php

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vacancy_number','title','department_id','job_position_id','branch_id','openings','open_date','close_date','status','created_by','candidate_number','job_vacancy_id','first_name','last_name','email','phone','employee_id','job_candidate_id','scheduled_at','panel','feedback','code','name','category','active','training_course_id','completed_at'])]
class JobVacancy extends Model
{
    protected $casts = ['active' => 'boolean', 'open_date' => 'date', 'close_date' => 'date', 'scheduled_at' => 'datetime', 'completed_at' => 'date'];
    public function department(): BelongsTo { return $this->belongsTo(\Modules\HospitalOperations\Models\Department::class); }
    public function position(): BelongsTo { return $this->belongsTo(JobPosition::class, 'job_position_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function candidates(): HasMany { return $this->hasMany(JobCandidate::class, 'job_vacancy_id'); }
    public function interviews(): HasMany { return $this->hasMany(CandidateInterview::class, 'job_candidate_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function enrollments(): HasMany { return $this->hasMany(TrainingEnrollment::class, 'training_course_id'); }
}
