<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code','name','contact','email','claims_contact','status','insurance_provider_id','coverage_percent','copay_fixed','annual_limit','per_visit_limit','requires_preauth','effective_from','effective_to','active','patient_id','insurance_plan_id','member_number','policy_number','verified_by','verified_at','auth_number','patient_insurance_membership_id','clinical_service_id','estimated_cost','approved_amount','approved_sessions','provider_ref','justification','created_by','claim_number','invoice_id','branch_id','amount','patient_share','insurer_share','paid_amount','submitted_at','submit_method','reject_reason','corporate_client_id','employee_number','benefit_limit','credit_limit','payment_terms_days'])]
class CorporateSponsorship extends Model
{
    protected $casts = ['active' => 'boolean', 'requires_preauth' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date', 'verified_at' => 'datetime', 'submitted_at' => 'datetime', 'coverage_percent' => 'decimal:2', 'copay_fixed' => 'decimal:2', 'annual_limit' => 'decimal:2', 'per_visit_limit' => 'decimal:2', 'estimated_cost' => 'decimal:2', 'approved_amount' => 'decimal:2', 'amount' => 'decimal:2', 'patient_share' => 'decimal:2', 'insurer_share' => 'decimal:2', 'paid_amount' => 'decimal:2', 'benefit_limit' => 'decimal:2', 'credit_limit' => 'decimal:2'];
    public function patient(): BelongsTo { return $this->belongsTo(\Modules\PatientCare\Models\Patient::class); }
    public function provider(): BelongsTo { return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id'); }
    public function membership(): BelongsTo { return $this->belongsTo(PatientInsuranceMembership::class, 'patient_insurance_membership_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function plans(): HasMany { return $this->hasMany(InsurancePlan::class, 'insurance_provider_id'); }
    public function claims(): HasMany { return $this->hasMany(InsuranceClaim::class, 'patient_insurance_membership_id'); }
    public function client(): BelongsTo { return $this->belongsTo(CorporateClient::class, 'corporate_client_id'); }
}
