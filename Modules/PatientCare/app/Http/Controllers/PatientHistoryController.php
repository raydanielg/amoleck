<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Foundation\Support\Audit;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;
use Modules\PatientCare\Services\RecordAmendmentService;

class PatientHistoryController extends Controller
{
    private static function rels(): array
    {
        return [
        'condition' => ['conditions', ['name' => 'required|string|max:255', 'description' => 'nullable|string', 'diagnosed_at' => 'nullable|date', 'status' => ['required', Rule::in(['active','resolved','chronic','historical','unverified'])], 'treatment_history' => 'nullable|string', 'source' => ['required', Rule::in(['patient_reported','clinician','document'])], 'verified' => 'boolean']],
        'surgical' => ['surgicalHistories', ['procedure' => 'required|string|max:255', 'description' => 'nullable|string', 'facility' => 'nullable|string|max:255', 'procedure_at' => 'nullable|date', 'outcome' => 'nullable|string|max:255', 'complications' => 'nullable|string', 'source' => ['required', Rule::in(['patient_reported','clinician','document'])]]],
        'medication' => ['medicationHistories', ['medication' => 'required|string|max:255', 'dosage' => 'nullable|string|max:255', 'frequency' => 'nullable|string|max:255', 'started_at' => 'nullable|date', 'ended_at' => 'nullable|date', 'status' => ['required', Rule::in(['current','discontinued'])], 'prescribing_source' => 'nullable|string|max:255', 'notes' => 'nullable|string']],
        'family' => ['familyHistories', ['relationship' => 'required|string|max:100', 'condition' => 'required|string|max:255', 'notes' => 'nullable|string', 'source' => ['required', Rule::in(['patient_reported','clinician','document'])]]],
        'social' => ['socialHistories', ['category' => ['required', Rule::in(['occupation','activity','smoking','alcohol','other'])], 'detail' => 'required|string|max:1000']],
        ];
    }

    public function store(Request $request, Patient $patient, string $kind, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $rels = static::rels();
        abort_unless(isset($rels[$kind]), 404);
        [$rel, $rules] = $rels[$kind];
        $data = $request->validate($rules);
        $patient->{$rel}()->create($data + ['recorded_by' => $request->user()->id]);
        Audit::record("patient.history_{$kind}_added", $patient, null, ['kind' => $kind], $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => ucfirst($kind).' recorded.']);
    }

    public function amend(Request $request, Patient $patient, string $kind, int $id, PatientAccessService $access, RecordAmendmentService $svc)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $rels = static::rels();
        abort_unless(isset($rels[$kind]), 404);
        abort_unless($request->user()->can('patientcare.history.amend'), 403);
        $data = $request->validate(['reason' => 'required|string|max:500', 'fields' => 'required|array']);
        $record = $patient->{$rels[$kind][0]}()->findOrFail($id);
        $svc->amend($patient, $record, $request->input('fields'), $data['reason'], $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Record amended (original preserved).']);
    }

    public function allergyStatus(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['allergy_status' => ['required', Rule::in(['known','no_known','not_assessed'])]]);
        $before = $patient->allergy_status;
        $patient->update($data);
        Audit::record('patient.allergy_status_changed', $patient, ['allergy_status' => $before], $data, $patient->primary_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Allergy status updated.']);
    }
}
