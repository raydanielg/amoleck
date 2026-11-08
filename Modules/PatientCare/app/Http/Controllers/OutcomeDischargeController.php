<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Clinical\Models\ClinicalOutcomeMeasure;
use Modules\PatientCare\Clinical\Models\ClinicalOutcomeRecord;
use Modules\PatientCare\Clinical\Models\ClinicalReassessment;
use Modules\PatientCare\Clinical\Models\ClinicalDischarge;
use Modules\PatientCare\Clinical\Services\OutcomeDischargeService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class OutcomeDischargeController extends Controller
{
    public function measures(Request $request)
    {
        return Inertia::render('admin/outcomes/index', [
            'measures' => ClinicalOutcomeMeasure::orderBy('category')->orderBy('name')->paginate(20),
            'categories' => ['pain', 'functional_mobility', 'joint_movement', 'muscle_performance', 'balance', 'neurological', 'cardiopulmonary', 'patient_reported'],
        ]);
    }

    public function storeMeasure(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'category' => 'required|string', 'scoring_format' => 'nullable|string', 'supported_units' => 'nullable|string', 'description' => 'nullable|string', 'interpretation' => 'nullable|string']);
        ClinicalOutcomeMeasure::create($data + ['code' => 'OM-'.str_pad((string) (ClinicalOutcomeMeasure::max('id') + 1), 4, '0', STR_PAD_LEFT), 'supported_units' => json_decode($data['supported_units'] ?? '["unit"]', true)]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Measure added.']);
    }

    public function recordOutcome(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'clinical_outcome_measure_id' => 'required|exists:clinical_outcome_measures,id',
            'clinical_treatment_plan_id' => 'nullable|exists:clinical_treatment_plans,id',
            'value_numeric' => 'nullable|numeric', 'value_categorical' => 'nullable|string|max:255', 'unit' => 'nullable|string|max:50',
            'status' => ['required', Rule::in(['assessed', 'not_assessed', 'not_applicable', 'unable'])],
            'not_assessed_reason' => 'required_unless:status,assessed|nullable|string|max:255',
            'assessed_at' => 'required|date', 'observations' => 'nullable|string',
        ]);
        $m = ClinicalOutcomeMeasure::findOrFail($data['clinical_outcome_measure_id']);
        if ($data['unit'] && $m->supported_units) {
            abort_unless(in_array($data['unit'], $m->supported_units), 422, 'Unit not supported by this measure.');
        }
        $patient->outcomeRecords()->create($data + ['branch_id' => $patient->primary_branch_id, 'clinician_id' => $request->user()->id]);
        Audit::record('clinical.outcome_recorded', $patient, null, ['measure' => $m->name], $patient->primary_branch_id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Outcome recorded.']);
    }

    public function comparison(Request $request, Patient $patient, OutcomeDischargeService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $measureId = $request->integer('measure_id');
        return response()->json(['comparison' => $svc->compare($measureId, $patient->id)]);
    }

    public function storeReassessment(Request $request, Patient $patient, OutcomeDischargeService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['clinical_treatment_plan_id' => 'nullable|exists:clinical_treatment_plans,id', 'branch_id' => 'required|exists:branches,id', 'clinical_encounter_id' => 'nullable|exists:clinical_encounters,id']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $r = ClinicalReassessment::create($data + ['patient_id' => $patient->id, 'reassessment_number' => $svc->nextNumber('REA'), 'physiotherapist_id' => $request->user()->id, 'created_by' => $request->user()->id]);
        Audit::record('clinical.reassessment_created', $patient, null, ['re' => $r->reassessment_number], $r->branch_id);
        return redirect()->route('admin.reassessments.show', $r);
    }

    public function showReassessment(Request $request, ClinicalReassessment $reassessment, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $reassessment->patient), 403);
        return Inertia::render('admin/reassessments/show', [
            'reassessment' => $reassessment->load(['patient:id,mrn,first_name,last_name', 'physiotherapist:id,name', 'goalReviews.goal']),
            'goals' => $reassessment->plan?->goals ?? [],
            'recommendations' => ['continue', 'modify_plan', 'change_frequency', 'change_interventions', 'hold', 'refer', 'discharge'],
            'goalStatuses' => OutcomeDischargeService::GOAL_REVIEW,
            'versions' => $reassessment->reVersions()->latest('version')->get(),
        ]);
    }

    public function updateReassessment(Request $request, ClinicalReassessment $reassessment, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $reassessment->patient), 403);
        abort_if(in_array($reassessment->status, ['finalized', 'amended', 'cancelled']), 422);
        DB::transaction(function () use ($reassessment, $request) {
            if ($r = $request->input('reassessment')) { $reassessment->update(array_filter($r, fn ($v) => $v !== null)); }
            if ($request->has('goal_reviews')) {
                $reassessment->goalReviews()->delete();
                foreach ($request->input('goal_reviews', []) as $row) {
                    if (empty($row['clinical_treatment_goal_id'])) continue;
                    abort_unless($reassessment->plan->goals()->where('id', $row['clinical_treatment_goal_id'])->exists(), 422, 'Goal not on this plan.');
                    if (($row['status'] ?? '') === 'achieved' && ! $request->user()->can('patientcare.clinical.reassessments.manage')) { continue; }
                    $gr = $reassessment->goalReviews()->create($row);
                    if (! empty($row['status'])) { $gr->goal->update(['status' => $row['status']]); }
                }
            }
        });
        return back()->with('toast', ['type' => 'success', 'message' => 'Reassessment saved.']);
    }

    public function transitionReassessment(Request $request, ClinicalReassessment $reassessment, OutcomeDischargeService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $reassessment->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'pending_review', 'finalized', 'amended', 'cancelled'])], 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can('patientcare.clinical.reassessments.manage'), 403);
        $svc->transitionReassessment($reassessment, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => 'Updated.']);
    }

    public function storeDischarge(Request $request, Patient $patient, OutcomeDischargeService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['clinical_treatment_plan_id' => 'nullable|exists:clinical_treatment_plans,id', 'branch_id' => 'required|exists:branches,id', 'type' => ['required', Rule::in(OutcomeDischargeService::DIS_TYPES)], 'discharge_on' => 'nullable|date']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $d = ClinicalDischarge::create($data + ['patient_id' => $patient->id, 'discharge_number' => $svc->nextNumber('DIS'), 'physiotherapist_id' => $request->user()->id, 'created_by' => $request->user()->id]);
        Audit::record('clinical.discharge_created', $patient, null, ['discharge' => $d->discharge_number], $d->branch_id);
        return redirect()->route('admin.discharges.show', $d);
    }

    public function showDischarge(Request $request, ClinicalDischarge $discharge, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $discharge->patient), 403);
        return Inertia::render('admin/discharges/show', [
            'discharge' => $discharge->load(['patient:id,mrn,first_name,last_name', 'physiotherapist:id,name', 'approvedBy:id,name']),
            'types' => OutcomeDischargeService::DIS_TYPES,
            'hep' => $discharge->patient->homeExercisePrograms()->where('status', 'active')->latest()->first(),
            'versions' => $discharge->versions()->latest('version')->get(),
        ]);
    }

    public function updateDischarge(Request $request, ClinicalDischarge $discharge, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $discharge->patient), 403);
        abort_if(in_array($discharge->status, ['finalized', 'amended', 'cancelled']), 422);
        $data = $request->validate(['baseline_pain' => 'nullable|integer|min:0|max:10', 'final_pain' => 'nullable|integer|min:0|max:10'] + array_fill_keys(['type','initial_status','final_status','functional_status','goal_achievement','outstanding_concerns','hep_recommendation','follow_up','precautions'], 'nullable'));
        $discharge->update(array_filter($data, fn ($v) => $v !== null));
        return back()->with('toast', ['type' => 'success', 'message' => 'Discharge saved.']);
    }

    public function transitionDischarge(Request $request, ClinicalDischarge $discharge, OutcomeDischargeService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $discharge->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'pending_review', 'approved', 'finalized', 'amended', 'cancelled'])], 'reason' => 'nullable|string|max:500']);
        $perm = $data['status'] === 'approved' ? 'patientcare.clinical.discharges.approve' : 'patientcare.clinical.discharges.manage';
        abort_unless($request->user()->can($perm), 403);
        if ($data['status'] === 'finalized') {
            abort_unless($discharge->type && $discharge->discharge_on, 422, 'Discharge type and date required.');
        }
        $svc->transitionDischarge($discharge, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => 'Updated.']);
    }

    public function printDischarge(Request $request, ClinicalDischarge $discharge, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $discharge->patient), 403);
        return view('clinical::clinical.discharge-print', ['discharge' => $discharge->load(['patient', 'physiotherapist', 'approvedBy', 'plan']), 'hospital' => \Modules\Foundation\Models\HospitalProfile::first(), 'sessions' => $discharge->patient->clinicalSessions()->where('status', 'finalized')->count()]);
    }
}
