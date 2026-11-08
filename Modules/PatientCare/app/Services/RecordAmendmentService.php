<?php

namespace Modules\PatientCare\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Models\Patient;

class RecordAmendmentService
{
    /** Controlled correction: original values preserved in patient_record_amendments. */
    public function amend(Patient $patient, $record, array $data, string $reason, int $userId): void
    {
        $changes = [];
        foreach ($data as $field => $new) {
            if ($record->{$field} != $new) {
                $changes[$field] = ['from' => $record->{$field}, 'to' => $new];
            }
        }
        abort_if(empty($changes), 422, 'No changes detected.');

        DB::transaction(function () use ($patient, $record, $data, $changes, $reason, $userId) {
            $patient->recordAmendments()->create([
                'record_type' => $record->getMorphClass() ?: get_class($record),
                'record_id' => $record->id,
                'changes' => $changes,
                'reason' => $reason,
                'amended_by' => $userId,
            ]);
            $record->update($data);
        });
        Audit::record('patient.record_amended', $patient, null, ['record_type' => get_class($record), 'record_id' => $record->id, 'reason' => $reason], $patient->primary_branch_id);
    }
}
