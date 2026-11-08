<?php

namespace Modules\PatientCare\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'branch_id', 'type', 'number', 'issuing_country', 'expires_at', 'name', 'relationship', 'phone', 'alt_phone', 'email', 'address', 'event', 'recorded_by', 'description', 'source', 'verified', 'category', 'path', 'original_name', 'mime', 'size', 'uploaded_by', 'status', 'consented_at', 'document_path', 'withdrawn_at', 'withdrawal_reason', 'from_status', 'to_status', 'reason', 'changed_by', 'matched_patient_id', 'matched_on', 'resolved_by', 'resolution', 'resolved_at'])]
class PatientIdentification extends Model
{
    protected $casts = ['date_of_birth' => 'date', 'expires_at' => 'date', 'consented_at' => 'date', 'withdrawn_at' => 'date', 'resolved_at' => 'datetime', 'verified' => 'boolean'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
