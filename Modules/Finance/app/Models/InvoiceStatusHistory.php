<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_number','patient_id','branch_id','admission_id','type','status','issue_date','due_date','subtotal','discount','tax','total','currency','cancel_reason','created_by','issued_by','invoice_id','patient_charge_id','service_name','service_date','quantity','unit_price','line_total','from_status','to_status','reason','changed_by','credit_number','amount','approved_by'])]
class InvoiceStatusHistory extends Model
{
    protected $casts = ['issue_date' => 'date', 'due_date' => 'date', 'service_date' => 'datetime', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'amount' => 'decimal:2'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function admission(): BelongsTo { return $this->belongsTo(\Modules\HospitalOperations\Models\Admission::class); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function statusHistories(): HasMany { return $this->hasMany(InvoiceStatusHistory::class); }
    public function creditNotes(): HasMany { return $this->hasMany(CreditNote::class); }
    public function charge(): BelongsTo { return $this->belongsTo(PatientCharge::class, 'patient_charge_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
