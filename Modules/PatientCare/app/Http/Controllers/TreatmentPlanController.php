<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Clinical\Models\ClinicalIntervention;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentGoal;
use Modules\PatientCare\Clinical\Models\ClinicalTreatmentPlan;
use Modules\PatientCare\Clinical\Services\TreatmentPlanService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class TreatmentPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = ClinicalTreatmentPlan::with('patient:id,mrn,first_name,last_name', 'physiotherapist:id,name', 'branch:id,name')
            ->when(! $request->user()->hasRole('Super Admin') && ! $request->user()->can('patientcare.treatment_plans.view_all_branches'),
                fn ($q) => $q->whereIn('branch_id', BranchContext::allowed($request->user())->pluck('id')->all() ?: [-1]))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('admin/treatment-plans/index', ['plans' => $plans, 'filters' => $request->only('status'), 'statuses' => ClinicalTreatmentPlan::STATUSES]);
    }

    public function create(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);

        return Inertia::render('admin/treatment-plans/form', [
            'patient' => $patient->only(['id', 'mrn', 'first_name', 'last_name']),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'encounters' => $patient->clinicalEncounters()->whereIn('status', ['finalized', 'amended'])->get(['id', 'encounter_number', 'type']),
        ]);
    }

    public function store(Request $request, Patient $patient, TreatmentPlanService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'clinical_encounter_id' => 'nullable|exists:clinical_encounters,id',
            'physiotherapist_id' => 'nullable|exists:users,id',
            'clinical_indication' => 'required|string|max:1000',
            'expected_outcomes' => 'nullable|string',
            'precautions' => 'nullable|string',
            'sessions_per_week' => 'nullable|integer|min:1|max:14',
            'expected_sessions' => 'nullable|integer|min:1|max:200',
            'expected_period' => 'nullable|string|max:100',
            'review_at' => 'nullable|date',
        ]);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $plan = $svc->create($data + ['patient_id' => $patient->id], $request->user()->id);

        return redirect()->route('admin.treatment-plans.show', $plan)->with('toast', ['type' => 'success', 'message' => "Plan {$plan->plan_number} created."]);
    }

    public function show(Request $request, ClinicalTreatmentPlan $plan, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $plan->patient), 403);

        return Inertia::render('admin/treatment-plans/show', [
            'plan' => $plan->load(['patient:id,mrn,first_name,last_name,allergy_status', 'branch:id,name', 'physiotherapist:id,name', 'approvedBy:id,name', 'encounter:id,encounter_number',
                'problems', 'goals.progressEntries', 'plannedInterventions.intervention:id,name,category', 'reviews.reviewer:id,name', 'versions.actedBy:id,name', 'precautionRecords']),
            'patientProblems' => $plan->patient->clinicalEncounters()->with('problems')->get()->flatMap->problems->unique('id')->values(['id', 'description', 'body_region', 'status']),
            'safety' => [
                'allergies' => $plan->patient->medicalEntries()->where('type', 'allergy')->pluck('description'),
                'conditions' => $plan->patient->conditions()->pluck('name'),
                'medications' => $plan->patient->medicationHistories()->where('status', 'current')->pluck('medication'),
                'red_flags' => $plan->patient->clinicalEncounters()->with('redFlags')->get()->flatMap->redFlags->pluck('description'),
            ],
            'catalogue' => ClinicalIntervention::where('active', true)->get(['id', 'name', 'category']),
            'statuses' => ClinicalTreatmentPlan::STATUSES,
        ]);
    }

    public function update(Request $request, ClinicalTreatmentPlan $plan, PatientAccessService $access, TreatmentPlanService $svc)
    {
        abort_unless($access->canAccess($request->user(), $plan->patient), 403);
        abort_if(in_array($plan->status, ['completed', 'discontinued', 'cancelled']), 422, 'Plan is closed.');
        abort_if(in_array($plan->status, ['approved', 'active', 'on_hold']) && ! $request->user()->can('patientcare.treatment_plans.approve'), 422, 'Approved plans require amendment permission.');

        $data = $request->validate([
            'plan' => 'nullable|array',
            'problems' => 'nullable|array',
            'problems.*.description' => 'required_with:problems|string|max:500',
            'goals' => 'nullable|array',
            'goals.*.description' => 'required_with:goals|string|max:500',
            'goals.*.type' => 'nullable|in:short_term,long_term',
            'goals.*.target_date' => 'nullable|date',
            'planned_interventions' => 'nullable|array',
            'precautions' => 'nullable|array',
            'precautions.*.description' => 'required_with:precautions|string|max:500',
        ]);

        DB::transaction(function () use ($plan, $data, $request) {
            if ($p = $request->input('plan')) {
                $plan->update(array_filter($p));
            }
            foreach ([['problems', 'problems'], ['goals', 'goals'], ['planned_interventions', 'plannedInterventions'], ['precautions', 'precautionRecords']] as [$key, $rel]) {
                if ($request->has($key)) {
                    $plan->{$rel}()->delete();
                    foreach ($request->input($key, []) as $row) {
                        if ($key === 'precautions') {
                            $row['recorded_by'] = $request->user()->id;
                        }
                        $plan->{$rel}()->create($row);
                    }
                }
            }
        });
        Audit::record('treatment_plan.updated', $plan->patient, null, ['plan' => $plan->plan_number], $plan->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Plan saved.']);
    }

    public function transition(Request $request, ClinicalTreatmentPlan $plan, TreatmentPlanService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $plan->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(ClinicalTreatmentPlan::STATUSES)], 'reason' => 'nullable|string|max:500', 'comments' => 'nullable|string|max:1000']);
        abort_unless($request->user()->can(TreatmentPlanService::PERMS[$data['status']]), 403);
        // self-approval guard: reviewer must differ from creator unless Super Admin
        if ($data['status'] === 'approved' && $plan->created_by === $request->user()->id && ! $request->user()->hasRole('Super Admin')) {
            abort(422, 'You cannot approve a plan you created.');
        }
        $svc->transition($plan, $data['status'], $request->user()->id, $data['reason'] ?? null, $data['comments'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => "Plan {$data['status']}."]);
    }

    public function goalProgress(Request $request, ClinicalTreatmentGoal $goal, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $goal->plan->patient), 403);
        $data = $request->validate(['value' => 'nullable|string|max:255', 'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'achieved', 'partially_achieved', 'not_achieved', 'discontinued'])], 'comment' => 'nullable|string|max:1000']);
        $goal->progressEntries()->create($data + ['recorded_by' => $request->user()->id]);
        if (! empty($data['status'])) {
            $goal->update(['status' => $data['status']]);
        }
        Audit::record('treatment_goal.progress', $goal->plan->patient, null, ['goal_id' => $goal->id], $goal->plan->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Progress recorded.']);
    }

    public function catalogue(Request $request)
    {
        return Inertia::render('admin/interventions/index', [
            'interventions' => ClinicalIntervention::orderBy('category')->orderBy('name')->paginate(20),
            'categories' => ['therapeutic_exercise', 'manual_therapy', 'physical_modalities', 'neurological_rehab', 'musculoskeletal_rehab', 'cardiopulmonary_rehab', 'other'],
        ]);
    }

    public function storeCatalogue(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'clinical_purpose' => 'nullable|string',
            'applicable_regions' => 'nullable|string|max:255',
            'contraindications' => 'nullable|string',
            'precautions' => 'nullable|string',
            'equipment' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);
        ClinicalIntervention::create($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Intervention added.']);
    }

    public function updateCatalogue(Request $request, ClinicalIntervention $intervention)
    {
        $data = $request->validate(['active' => 'required|boolean'] + array_fill_keys(['name', 'category', 'description', 'clinical_purpose', 'applicable_regions', 'contraindications', 'precautions', 'equipment'], 'nullable'));
        $intervention->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('toast', ['type' => 'success', 'message' => 'Intervention updated.']);
    }

    public function print(Request $request, ClinicalTreatmentPlan $plan, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $plan->patient), 403);

        return view('clinical::clinical.plan-print', [
            'plan' => $plan->load(['patient', 'branch', 'physiotherapist', 'approvedBy', 'encounter', 'problems', 'goals', 'plannedInterventions.intervention', 'precautionRecords']),
            'hospital' => \Modules\Foundation\Models\HospitalProfile::first(),
        ]);
    }
}
