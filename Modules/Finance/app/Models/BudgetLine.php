<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference','name','accounting_period_id','type','status','total','created_by','approved_by','reject_reason','budget_id','chart_of_account_id','department_id','branch_id','category','description','annual_amount','revised_amount','budget_line_id','amount','source','source_id','requisition_number','estimated_amount','financial_status','justification','financial_approved_by'])]
class BudgetLine extends Model
{
    protected $casts = ['total' => 'decimal:2', 'annual_amount' => 'decimal:2', 'revised_amount' => 'decimal:2', 'amount' => 'decimal:2', 'estimated_amount' => 'decimal:2'];
    public function period(): BelongsTo { return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id'); }
    public function lines(): HasMany { return $this->hasMany(BudgetLine::class); }
    public function commitments(): HasMany { return $this->hasMany(BudgetCommitment::class, 'budget_line_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(\Modules\HospitalOperations\Models\Department::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }
}
