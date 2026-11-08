<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentGoal;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentSession;
use Modules\PatientCare\Clinical\Services\TreatmentSessionService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class TreatmentSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = ClinicalTreatmentSession::with('patient:id,mrn,first_name,last_name', 'treatingPhysiotherapist:id,name', 'branch:id,name');
        if (! $user->hasRole('Super Admin') && ! $user->can('patientcare.clinical.sessions.view_all_branches')) {
            $q->whereIn('branch_id', BranchContext::allowed($user)->pluck('id')->all() ?: [-1]);
        }
        $sessions = $q->when($request->status, fn ($q, $s) => $q->where('status', $s))->latest('session_at')->paginate(15)->withQueryString();

        return Inertia::render('admin/sessions/index', [
            'sessions' => $sessions,
            'stats' => ['today' => (clone $q)->whereDate('session_at', today())->count(), 'draft' => (clone $q)->where('status', 'draft')->count(), 'pending' => (clone $q)->where('status', 'pending_review')->count(), 'finalized' => (clone $q)->where('status', 'finalized')->count()],
            'filters' => $request->only('status'), 'statuses' => ClinicalTreatmentSession::STATUSES,
        ]);
    }

    public function create(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $plan = $patient->treatmentPlans()->where('status', 'active')->latest()->first();
        abort_unless($plan, 422, 'Patient has no active treatment plan.');

        return Inertia::render('admin/sessions/form', [
            'patient' => $patient->only(['id', 'mrn', 'first_name', 'last_name']),
            'plan' => $plan->only(['id', 'plan_number']),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
        ]);
    }

    public function store(Request $request, Patient $patient, TreatmentSessionService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'clinical_treatment_plan_id' => 'required|exists:clinical_treatment_plans,id',
            'branch_id' => 'required|exists:branches,id',
            'type' => ['required', Rule::in(ClinicalTreatmentSession::TYPES)],
            'session_at' => 'required|date',
        ]);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $plan = \Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan::findOrFail($data['clinical_treatment_plan_id']);
        abort_unless($plan->patient_id === $patient->id && $plan->status === 'active', 422, 'Session must target the patient\'s active plan.');
        $s = $svc->create($data + ['patient_id' => $patient->id, 'treating_physiotherapist_id' => $request->user()->id], $request->user()->id);

        return redirect()->route('admin.sessions.show', $s)->with('toast', ['type' => 'success', 'message' => "Session {$s->session_number} created."]);
    }

    public function show(Request $request, ClinicalTreatmentSession $session, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $session->patient), 403);

        return Inertia::render('admin/sessions/show', [
            'session' => $session->load(['patient:id,mrn,first_name,last_name,allergy_status', 'branch:id,name', 'treatingPhysiotherapist:id,name', 'plan:id,plan_number,status', 'interventions', 'notes', 'adverseEvents', 'reviews.reviewer:id,name', 'versions.actedBy:id,name']),
            'plannedInterventions' => $session->plan?->plannedInterventions()->with('intervention:id,name')->get() ?? [],
            'goals' => $session->plan?->goals()->with('progressEntries')->get() ?? [],
            'types' => ClinicalTreatmentSession::TYPES,
            'tolerances' => ClinicalTreatmentSession::TOLERANCES,
        ]);
    }

    public function update(Request $request, ClinicalTreatmentSession $session, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $session->patient), 403);
        abort_if(in_array($session->status, ['finalized', 'amended', 'cancelled']), 422, 'Finalized sessions cannot be edited — use amendment.');

        $request->validate([
            'session.pre_pain' => 'nullable|integer|min:0|max:10',
            'session.post_pain' => 'nullable|integer|min:0|max:10',
            'interventions' => 'nullable|array',
            'notes' => 'nullable|array',
            'adverse_events' => 'nullable|array',
        ]);

        DB::transaction(function () use ($session, $request) {
            if ($s = $request->input('session')) {
                $session->update(array_filter($s, fn ($v) => $v !== null));
            }
            if ($request->has('interventions')) {
                $session->interventions()->delete();
                foreach ($request->input('interventions', []) as $row) {
                    if (empty($row['name'])) continue;
                    $session->interventions()->create($row);
                }
            }
            if ($n = $request->input('notes')) {
                $session->notes()->updateOrCreate([], $n);
            }
            if ($request->has('adverse_events')) {
                $session->adverseEvents()->delete();
                foreach ($request->input('adverse_events', []) as $row) {
                    if (empty($row['description'])) continue;
                    $session->adverseEvents()->create($row + ['recorded_by' => $request->user()->id]);
                }
            }
        });
        Audit::record('clinical.session_updated', $session->patient, null, ['session' => $session->session_number], $session->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Session saved.']);
    }

    public function transition(Request $request, ClinicalTreatmentSession $session, TreatmentSessionService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $session->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(ClinicalTreatmentSession::STATUSES)], 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can(TreatmentSessionService::PERMS[$data['status']]), 403);
        $svc->transition($session, $data['status'], $request->user()->id, $data['reason'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => "Session {$data['status']}."]);
    }

    public function amend(Request $request, ClinicalTreatmentSession $session, TreatmentSessionService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $session->patient), 403);
        abort_unless($request->user()->can('patientcare.clinical.sessions.amend'), 403);
        abort_unless($session->status === 'finalized' || $session->status === 'amended', 422);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $svc->snapshot($session, 'amended', $request->user()->id, $data['reason']);
        $session->update(['status' => 'amended']);
        Audit::record('clinical.session_amended', $session->patient, null, ['reason' => $data['reason']], $session->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Amendment recorded.']);
    }

    public function goalProgress(Request $request, ClinicalTreatmentSession $session, ClinicalTreatmentGoal $goal, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $session->patient), 403);
        abort_unless($goal->clinical_treatment_plan_id === $session->clinical_treatment_plan_id, 403);
        $data = $request->validate(['value' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'improving', 'no_change', 'deteriorating', 'achieved', 'not_achieved', 'not_assessed'])], 'comment' => 'nullable|string|max:1000']);
        $goal->progressEntries()->create($data + ['recorded_by' => $request->user()->id, 'clinical_treatment_session_id' => $session->id]);
        if (! empty($data['status'])) {
            $goal->update(['status' => $data['status']]);
        }
        Audit::record('clinical.session_goal_progress', $session->patient, null, ['goal_id' => $goal->id, 'session' => $session->session_number], $session->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Progress recorded.']);
    }

    public function print(Request $request, ClinicalTreatmentSession $session, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $session->patient), 403);

        return view('clinical::clinical.session-print', [
            'session' => $session->load(['patient', 'branch', 'treatingPhysiotherapist', 'plan', 'interventions', 'notes', 'adverseEvents']),
            'hospital' => \Modules\Foundation\Models\HospitalProfile::first(),
        ]);
    }
}
