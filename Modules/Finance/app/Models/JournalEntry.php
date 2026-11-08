<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','type','parent_id','posting','active','starts_at','ends_at','status','journal_number','journal_date','accounting_period_id','branch_id','description','source','source_id','created_by','posted_by','posted_at','journal_entry_id','chart_of_account_id','direction','amount','memo','expense_number','category','payee','expense_date','approved_by'])]
class JournalEntry extends Model
{
    protected $casts = ['posting' => 'boolean', 'active' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date', 'journal_date' => 'date', 'posted_at' => 'datetime', 'expense_date' => 'date', 'amount' => 'decimal:2'];
    public function parent(): BelongsTo { return $this->belongsTo(static::class, 'parent_id'); }
    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
