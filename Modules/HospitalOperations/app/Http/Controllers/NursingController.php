<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\ClinicalEscalation;
use Modules\HospitalOperations\Models\FluidBalanceEntry;
use Modules\HospitalOperations\Models\NursePatientAssignment;
use Modules\HospitalOperations\Models\NursingHandover;
use Modules\HospitalOperations\Models\NursingNote;
use Modules\HospitalOperations\Models\VitalSign;
use Modules\HospitalOperations\Services\NursingService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class NursingController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        return Inertia::render('admin/nursing/index', [
            'admissions' => Admission::where('branch_id', $branchId)->where('status', 'admitted')->with('patient:id,mrn,first_name,last_name', 'bed:id,code', 'ward:id,name')->get(),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'nurses' => \App\Models\User::role(['Registered Nurse', 'Nursing Assistant', 'Physiotherapist'])->get(['id', 'name']),
            'vitals' => array_keys(NursingService::VITAL_UNITS),
            'openEscalations' => ClinicalEscalation::where('branch_id', $branchId)->where('status', 'open')->with('patient:id,mrn,first_name,last_name', 'reportedBy:id,name')->latest()->get(),
            'pendingHandovers' => NursingHandover::where('branch_id', $branchId)->where('status', 'submitted')->count(),
        ]);
    }

    public function recordVital(Request $request, NursingService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'admission_id' => 'nullable|exists:admissions,id',
            'kind' => ['required', Rule::in(array_keys(NursingService::VITAL_UNITS))], 'value_num' => 'nullable|numeric',
            'value_text' => 'nullable|string|max:50', 'taken_at' => 'required|date', 'notes' => 'nullable',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $adm = $data['admission_id'] ? Admission::find($data['admission_id']) : null;
        $data['branch_id'] = $adm?->branch_id ?? $patient->primary_branch_id;
        $data['ward_id'] = $adm?->ward_id;
        $data['unit'] = NursingService::VITAL_UNITS[$data['kind']];
        $svc->recordVital($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Vital recorded.']);
    }

    public function vitalsHistory(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        return response()->json(VitalSign::where('patient_id', $patient->id)->with('recordedBy:id,name')->latest('taken_at')->get());
    }

    public function storeNote(Request $request, NursingService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'admission_id' => 'nullable|exists:admissions,id',
            'category' => 'required|string', 'shift' => 'nullable', 'subjective' => 'nullable', 'objective' => 'nullable',
            'assessment' => 'nullable', 'actions' => 'nullable', 'response' => 'nullable',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data['branch_id'] = $data['admission_id'] ? Admission::find($data['admission_id'])->branch_id : $patient->primary_branch_id;
        $svc->createNote($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Note saved.']);
    }

    public function finalizeNote(Request $request, NursingNote $note, NursingService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $note->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('nursing.notes.finalize'), 403);
        $svc->finalizeNote($note, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Finalized.']);
    }

    public function amendNote(Request $request, NursingNote $note, NursingService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $note->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('nursing.notes.amend'), 403);
        $data = $request->validate(['subjective' => 'nullable', 'objective' => 'nullable', 'assessment' => 'nullable', 'actions' => 'nullable', 'response' => 'nullable', 'reason' => 'required|string|max:500']);
        $svc->amendNote($note, array_diff_key($data, array_flip(['reason'])), $request->user()->id, $data['reason']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Amended — previous version preserved.']);
    }

    public function storeFluid(Request $request, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'admission_id' => 'required|exists:admissions,id',
            'direction' => ['required', Rule::in(['intake', 'output'])], 'category' => 'required|string',
            'amount' => 'required|numeric|min:0|max:99999', 'recorded_at' => 'required|date', 'shift' => 'nullable', 'notes' => 'nullable',
        ]);
        abort_unless($access->canAccess($request->user(), Patient::findOrFail($data['patient_id'])), 403);
        FluidBalanceEntry::create($data + ['recorded_by' => $request->user()->id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Recorded.']);
    }

    public function assign(Request $request)
    {
        abort_unless($request->user()->can('nursing.assignments.manage'), 403);
        $data = $request->validate(['admission_id' => 'required|exists:admissions,id', 'nurse_id' => 'required|exists:users,id', 'type' => 'nullable|string']);
        $adm = Admission::findOrFail($data['admission_id']);
        NursePatientAssignment::create($data + ['patient_id' => $adm->patient_id, 'branch_id' => $adm->branch_id, 'ward_id' => $adm->ward_id, 'assigned_by' => $request->user()->id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Assigned.']);
    }

    public function escalate(Request $request, NursingService $svc, PatientAccessService $access)
    {
        $data = $request->validate(['patient_id' => 'required|exists:patients,id', 'admission_id' => 'nullable|exists:admissions,id', 'concern' => 'required|string|max:1000', 'clinician_id' => 'nullable|exists:users,id']);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data['branch_id'] = $data['admission_id'] ? Admission::find($data['admission_id'])->branch_id : $patient->primary_branch_id;
        $svc->escalate($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Escalated.']);
    }

    public function acknowledgeEscalation(Request $request, ClinicalEscalation $escalation, NursingService $svc)
    {
        abort_unless($request->user()->can('nursing.escalations.manage'), 403);
        $svc->acknowledgeEscalation($escalation, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Acknowledged.']);
    }

    public function storeHandover(Request $request, NursingService $svc)
    {
        $data = $request->validate(['branch_id' => 'required|exists:branches,id', 'ward_id' => 'nullable|exists:wards,id', 'outgoing_shift' => 'nullable', 'incoming_shift' => 'nullable', 'summary' => 'nullable', 'patients' => 'nullable|array']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $svc->createHandover($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Handover submitted.']);
    }

    public function acknowledgeHandover(Request $request, NursingHandover $handover, NursingService $svc)
    {
        $svc->acknowledgeHandover($handover, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Acknowledged.']);
    }

    public function fluidBalance(Request $request, NursingService $svc)
    {
        $data = $request->validate(['admission_id' => 'required|exists:admissions,id', 'date' => 'nullable|date']);
        return response()->json($svc->fluidBalance($data['admission_id'], $data['date'] ?? null));
    }
}
