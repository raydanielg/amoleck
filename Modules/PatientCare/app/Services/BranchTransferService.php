<?php

namespace Modules\PatientCare\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Models\Patient;

class BranchTransferService
{
    public function transfer(Patient $patient, int $toBranchId, ?string $reason, int $userId): void
    {
        abort_if($patient->primary_branch_id === $toBranchId, 422, 'Patient is already assigned to this branch.');
        $from = $patient->primary_branch_id;

        DB::transaction(function () use ($patient, $toBranchId, $from, $reason, $userId) {
            $patient->branchTransfers()->create([
                'from_branch_id' => $from, 'to_branch_id' => $toBranchId,
                'reason' => $reason, 'transferred_by' => $userId,
            ]);
            $patient->update(['primary_branch_id' => $toBranchId]); // MRN + registration_branch untouched
            $patient->branches()->syncWithoutDetaching([$toBranchId => ['event' => 'transferred', 'recorded_by' => $userId]]);
        });
        Audit::record('patient.transferred', $patient, ['primary_branch_id' => $from], ['primary_branch_id' => $toBranchId], $toBranchId);
    }
}
