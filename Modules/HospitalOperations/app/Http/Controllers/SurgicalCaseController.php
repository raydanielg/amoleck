<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Department;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalProcedure;
use Modules\HospitalOperations\Models\SurgicalSpecialty;
use Modules\HospitalOperations\Services\SurgicalCaseService;
use Modules\HospitalOperations\Services\SurgicalIntegrationService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class SurgicalCaseController extends Controller
{
    private function guardCase(Request $request, SurgicalCase $case): void
    {
        if (! BranchContext::canAccess($request->user(), (int) $case->branch_id)) {
            fwrite(STDERR, "GUARD-FAIL user=".var_export($request->user()?->id, true)." caseBranch=".var_export($case->branch_id, true)." allowed=".BranchContext::allowed($request->user())->pluck('id')->implode(',')."\n");
            abort(403);
        }
        abort_unless(app(PatientAccessService::class)->canAccess($request->user(), $case->patient), 403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $allowed = BranchContext::allowed($user)->pluck('id');
        $branchId = $request->integer('branch_id');
        return Inertia::render('admin/surgery/cases/index', [
            'cases' => SurgicalCase::whereIn('branch_id', $branchId && $allowed->contains($branchId) ? [$branchId] : $allowed->all())
                ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->get('priority'), fn ($q, $p) => $q->where('priority', $p))
                ->when($request->get('q'), fn ($q, $v) => $q->where(fn ($x) => $x->where('case_number', 'like', "%{$v}%")->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%")->orWhere('mrn', 'like', "%{$v}%"))))
                ->with(['patient:id,mrn,first_name,last_name', 'specialty:id,name', 'procedure:id,name', 'surgeon:id,name', 'branch:id,name'])
                ->latest()->paginate(20)->withQueryString(),
            'statuses' => SurgicalCase::STATUSES,
            'priorities' => SurgicalCase::PRIORITIES,
            'filters' => $request->only(['status', 'priority', 'q', 'branch_id']),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
        ]);
    }

    public function create(Request $request, SurgicalIntegrationService $int)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        return Inertia::render('admin/surgery/cases/form', [
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->where('status', 'active')->limit(100)->get(),
            'specialties' => SurgicalSpecialty::where('status', 'active')->with('procedures:id,surgical_specialty_id,name,code,status,expected_duration_minutes')->get(['id', 'code', 'name']),
            'procedures' => SurgicalProcedure::where('status', 'active')->get(['id', 'code', 'name', 'surgical_specialty_id', 'category', 'expected_duration_minutes']),
            'departments' => Department::where('status', 'active')->get(['id', 'name']),
            'staff' => $branchId ? $int->eligibleStaff($branchId) : [],
            'priorities' => SurgicalCase::PRIORITIES,
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
        ]);
    }

    public function store(Request $request, SurgicalCaseService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id', 'surgical_specialty_id' => 'nullable|exists:surgical_specialties,id',
            'surgical_procedure_id' => 'nullable|exists:surgical_procedures,id',
            'surgeon_id' => 'nullable|exists:users,id', 'referring_clinician_id' => 'nullable|exists:users,id',
            'clinical_encounter_id' => 'nullable|exists:clinical_encounters,id',
            'indication' => 'required|string|max:2000', 'diagnosis' => 'nullable|string|max:2000',
            'priority' => ['required', Rule::in(SurgicalCase::PRIORITIES)],
            'proposed_date' => 'nullable|date', 'notes' => 'nullable|string|max:2000',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $c = $svc->create($data, $request->user()->id);
        return redirect()->route('admin.surgery.cases.show', $c)->with('toast', ['type' => 'success', 'message' => "Case {$c->case_number} created."]);
    }

    public function show(Request $request, SurgicalCase $case, SurgicalCaseService $svc)
    {
        $this->guardCase($request, $case);
        $case->load([
            'patient:id,mrn,first_name,middle_name,last_name,gender,date_of_birth', 'specialty:id,name', 'procedure:id,name,code,category,expected_duration_minutes,consent_required,anesthesia_options',
            'surgeon:id,name', 'orderingClinician:id,name', 'statusHistories.actor:id,name', 'consultation.versions',
            'preopAssessment.versions', 'anesthesiaPreassessment.versions', 'consents.versions', 'checklistItems',
            'bookings.theatre:id,code,name', 'bookings.surgeon:id,name', 'teamAssignments.user:id,name', 'teamAssignments.employee:id,employee_number',
            'operations.procedures', 'operations.operativeNote', 'operations.anesthesiaRecord', 'operations.counts', 'operations.specimens',
            'safetyVerifications.items', 'safetyExceptions', 'bloodRequirements', 'recoveryRecord.bed', 'recoveryRecord.assessments', 'handover',
            'diagnoses',
        ]);
        return Inertia::render('admin/surgery/cases/show', [
            'case' => $case,
            'blockers' => $svc->readinessBlockers($case),
            'transitions' => SurgicalCaseService::TRANSITIONS[$case->status] ?? [],
            'priorities' => SurgicalCase::PRIORITIES,
            'users' => \App\Models\User::where('status', 'active')->get(['id', 'name', 'job_title']),
            'statuses' => SurgicalCase::STATUSES,
        ]);
    }

    public function transition(Request $request, SurgicalCase $case, SurgicalCaseService $svc)
    {
        $this->guardCase($request, $case);
        $data = $request->validate(['status' => 'required|string', 'reason' => 'nullable|string|max:500', 'approval_reference' => 'nullable|string|max:120']);
        $perm = match ($data['status']) {
            'approved' => 'surgery.cases.approve',
            'rejected' => 'surgery.cases.approve',
            'cancelled' => 'surgery.cases.cancel',
            'postponed' => 'surgery.cases.postpone',
            'submitted' => 'surgery.cases.submit',
            default => 'surgery.cases.update',
        };
        abort_unless($request->user()->can($perm), 403);
        $svc->transition($case, $data['status'], $request->user()->id, $data['reason'] ?? null, $data['approval_reference'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Status: {$data['status']}"]);
    }

    public function saveConsultation(Request $request, SurgicalCase $case, SurgicalCaseService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.cases.update'), 403);
        $data = $request->validate([
            'presenting_complaint' => 'nullable|string', 'clinical_history' => 'nullable|string',
            'previous_treatment' => 'nullable|string', 'examination_summary' => 'nullable|string',
            'indication' => 'nullable|string', 'proposed_procedure' => 'nullable|string',
            'alternatives' => 'nullable|string', 'risks' => 'nullable|string', 'expected_benefits' => 'nullable|string',
            'recommendation' => 'nullable|string',
            'decision' => 'required|in:recommended,accepted,deferred,rejected,further_assessment,referred',
        ]);
        $svc->saveConsultation($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Consultation saved.']);
    }

    public function addDiagnosis(Request $request, SurgicalCase $case)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.cases.update'), 403);
        $data = $request->validate(['type' => 'required|in:working,preop,postop', 'description' => 'required|string|max:1000']);
        $case->diagnoses()->create($data + ['recorded_by' => $request->user()->id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Diagnosis recorded.']);
    }

    public function cancel(Request $request, SurgicalCase $case, SurgicalCaseService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.cases.cancel'), 403);
        $data = $request->validate(['category' => 'nullable|string', 'reason' => 'required|string|max:1000', 'patient_notified' => 'nullable|boolean']);
        $svc->cancel($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Case cancelled.']);
    }

    public function postpone(Request $request, SurgicalCase $case, SurgicalCaseService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.cases.postpone'), 403);
        $data = $request->validate(['category' => 'nullable|string', 'reason' => 'required|string|max:1000', 'rescheduled_to' => 'nullable|date', 'patient_notified' => 'nullable|boolean']);
        $svc->postpone($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Case postponed.']);
    }
}
