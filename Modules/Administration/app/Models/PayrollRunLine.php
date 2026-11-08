<?php

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','type','taxable','rate','fixed_amount','active','employee_id','basic_salary','effective_from','effective_to','status','approved_by','created_by','ceiling','source','principal','installment','remaining','start_date','end_date','cutoff_date','run_number','payroll_period_id','branch_id','gross','deductions','net','employer_contrib','finalized_at','payroll_run_id','component','direction','amount','memo','payslip_number'])]
class PayrollRunLine extends Model
{
    protected $casts = ['taxable' => 'boolean', 'active' => 'boolean', 'rate' => 'decimal:4', 'fixed_amount' => 'decimal:2', 'basic_salary' => 'decimal:2', 'ceiling' => 'decimal:2', 'principal' => 'decimal:2', 'installment' => 'decimal:2', 'remaining' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'cutoff_date' => 'date', 'effective_from' => 'date', 'effective_to' => 'date', 'gross' => 'decimal:2', 'deductions' => 'decimal:2', 'net' => 'decimal:2', 'employer_contrib' => 'decimal:2', 'amount' => 'decimal:2', 'finalized_at' => 'datetime'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function period(): BelongsTo { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
    public function lines(): HasMany { return $this->hasMany(PayrollRunLine::class, 'payroll_run_id'); }
    public function payslip(): HasMany { return $this->hasMany(Payslip::class, 'payroll_run_id'); }
    public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
}
