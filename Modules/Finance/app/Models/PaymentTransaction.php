<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','type','branch_id','active','payment_number','patient_id','invoice_id','payment_method_id','method','provider','amount','currency','reference','status','initiated_at','confirmed_at','idempotency_key','recorded_by','verified_by','cashier_shift_id','payment_transaction_id','allocated_by','reversal_reason','refund_number','reason','requested_by','approved_by','completed_at','cashier_id','opening_balance','expected_closing','actual_closing','variance','opened_at','closed_at'])]
class PaymentTransaction extends Model
{
    protected $casts = ['active' => 'boolean', 'amount' => 'decimal:2', 'initiated_at' => 'datetime', 'confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_balance' => 'decimal:2', 'expected_closing' => 'decimal:2', 'actual_closing' => 'decimal:2', 'variance' => 'decimal:2'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class, 'payment_transaction_id'); }
    public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class, 'payment_transaction_id'); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'recorded_by'); }
    public function cashier(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'cashier_id'); }
}
