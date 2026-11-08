<?php

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','category','department_id','weight','target','unit','active','start_date','end_date','status','employee_id','performance_kpi_id','due_date','review_number','performance_cycle_id','reviewer_id','self_score','supervisor_score','final_score','comments','disagreement','created_by','approved_by','pip_number','performance_review_id','objective','review_date'])]
class PerformanceCycle extends Model
{
    protected $casts = ['active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date', 'due_date' => 'date', 'review_date' => 'date', 'weight' => 'decimal:2', 'target' => 'decimal:2', 'self_score' => 'decimal:2', 'supervisor_score' => 'decimal:2', 'final_score' => 'decimal:2'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function cycle(): BelongsTo { return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id'); }
    public function kpi(): BelongsTo { return $this->belongsTo(PerformanceKpi::class, 'performance_kpi_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'reviewer_id'); }
    public function review(): BelongsTo { return $this->belongsTo(PerformanceReview::class, 'performance_review_id'); }
    public function pips(): HasMany { return $this->hasMany(PerformanceImprovementPlan::class, 'performance_review_id'); }
}
