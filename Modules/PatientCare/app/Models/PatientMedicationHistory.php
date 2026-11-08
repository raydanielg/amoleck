<?php

namespace Modules\PatientCare\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'branch_id', 'name', 'description', 'diagnosed_at', 'status', 'treatment_history', 'source', 'verified', 'recorded_by', 'procedure', 'facility', 'procedure_at', 'outcome', 'complications', 'medication', 'dosage', 'frequency', 'started_at', 'ended_at', 'prescribing_source', 'notes', 'relationship', 'condition', 'category', 'detail', 'ref_number', 'type', 'referral_date', 'receiving_branch_id', 'referrer_name', 'referrer_facility', 'referrer_designation', 'referrer_contact', 'referrer_address', 'reason', 'presenting_complaint', 'relevant_history', 'preliminary_diagnosis', 'requested_service', 'registered_by', 'patient_referral_id', 'from_status', 'to_status', 'changed_by', 'patient_document_id', 'path', 'original_name', 'replaced_by', 'from_branch_id', 'to_branch_id', 'transferred_by', 'acknowledged_by', 'acknowledged_at', 'record_type', 'record_id', 'changes', 'amended_by'])]
class PatientMedicationHistory extends Model
{
    protected $casts = ['diagnosed_at' => 'date', 'procedure_at' => 'date', 'started_at' => 'date', 'ended_at' => 'date', 'referral_date' => 'date', 'acknowledged_at' => 'datetime', 'verified' => 'boolean', 'changes' => 'array'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'recorded_by');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }
}
