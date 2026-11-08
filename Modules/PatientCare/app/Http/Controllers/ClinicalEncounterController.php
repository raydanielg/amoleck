<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Clinical\Models\ClinicalEncounter;
use Modules\PatientCare\Clinical\Services\ClinicalEncounterService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class ClinicalEncounterController extends Controller
{
    public function dashboard(Request $request, PatientAccessService $access)
    {
        $user = $request->user();
        $q = ClinicalEncounter::query();
        if (! $user->hasRole('Super Admin') && ! $user->can('patientcare.clinical.history.view_all_branches')) {
            $q->whereIn('branch_id', BranchContext::allowed($user)->pluck('id')->all() ?: [-1]);
        }

        return Inertia::render('admin/clinical/dashboard', [
            'stats' => [
                'total' => (clone $q)->count(),
                'draft' => (clone $q)->where('status', 'draft')->count(),
                'inProgress' => (clone $q)->where('status', 'in_progress')->count(),
                'pendingReview' => (clone $q)->where('status', 'pending_review')->count(),
                'finalized' => (clone $q)->where('status', 'finalized')->count(),
                'mine' => (clone $q)->where('physiotherapist_id', $user->id)->count(),
            ],
            'recent' => (clone $q)->with('patient:id,mrn,first_name,last_name', 'physiotherapist:id,name')->latest()->limit(10)->get(['id', 'encounter_number', 'patient_id', 'physiotherapist_id', 'type', 'status', 'encounter_at']),
        ]);
    }

    public function index(Request $request, PatientAccessService $access)
    {
        $encounters = ClinicalEncounter::with('patient:id,mrn,first_name,last_name', 'physiotherapist:id,name', 'branch:id,name')
            ->when(! $request->user()->hasRole('Super Admin') && ! $request->user()->can('patientcare.clinical.history.view_all_branches'),
                fn ($q) => $q->whereIn('branch_id', BranchContext::allowed($request->user())->pluck('id')->all() ?: [-1]))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('encounter_at')->paginate(15)->withQueryString();

        return Inertia::render('admin/clinical/index', ['encounters' => $encounters, 'filters' => $request->only('status'), 'statuses' => ClinicalEncounter::STATUSES]);
    }

    public function create(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);

        return Inertia::render('admin/clinical/form', [
            'patient' => $patient->only(['id', 'mrn', 'first_name', 'last_name']),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
        ]);
    }

    public function store(Request $request, Patient $patient, ClinicalEncounterService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(ClinicalEncounter::TYPES)],
            'encounter_at' => 'required|date',
            'branch_id' => 'required|exists:branches,id',
            'physiotherapist_id' => 'nullable|exists:users,id',
            'referring_professional' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $enc = $svc->create($data + ['patient_id' => $patient->id], $request->user()->id);

        return redirect()->route('admin.patients.clinical.show', $enc)->with('toast', ['type' => 'success', 'message' => "Encounter {$enc->encounter_number} created."]);
    }

    public function show(Request $request, ClinicalEncounter $encounter, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $encounter->patient), 403);

        return Inertia::render('admin/clinical/show', [
            'encounter' => $encounter->load(['patient:id,mrn,first_name,last_name,gender,date_of_birth,allergy_status', 'branch:id,name', 'physiotherapist:id,name', 'assessment', 'complaints', 'painAssessments', 'functionalEntries', 'objectiveFindings', 'romMeasurements', 'strengthMeasurements', 'specialTests', 'redFlags', 'problems', 'bodyMapAnnotations', 'versions.actedBy:id,name']),
            'patientHistory' => $encounter->patient->load(['conditions', 'medicationHistories', 'surgicalHistories'])->only(['conditions', 'medication_histories', 'surgical_histories']),
            'types' => ClinicalEncounter::TYPES,
        ]);
    }

    /** Bulk update of all draft sections — replace-style sync; finalized encounters rejected. */
    public function update(Request $request, ClinicalEncounter $encounter, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $encounter->patient), 403);
        abort_if($encounter->isFinalized() || $encounter->status === 'cancelled', 422, 'Finalized encounters cannot be edited — use amendment.');

        $data = $request->validate([
            'encounter' => 'nullable|array',
            'assessment' => 'nullable|array',
            'complaints' => 'nullable|array',
            'pain' => 'nullable|array',
            'functional' => 'nullable|array',
            'objective' => 'nullable|array',
            'rom' => 'nullable|array',
            'strength' => 'nullable|array',
            'special_tests' => 'nullable|array',
            'red_flags' => 'nullable|array',
            'problems' => 'nullable|array',
            'body_map' => 'nullable|array',
            'pain.*.nprs_current' => 'nullable|integer|min:0|max:10',
            'pain.*.nprs_best' => 'nullable|integer|min:0|max:10',
            'pain.*.nprs_worst' => 'nullable|integer|min:0|max:10',
            'strength.*.grade' => 'nullable|integer|min:0|max:5',
            'rom.*.active_rom' => 'nullable|numeric',
            'rom.*.passive_rom' => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($encounter, $data, $request) {
            if ($enc = $data['encounter'] ?? null) {
                $encounter->update(array_filter($enc) + ['updated_by' => $request->user()->id]);
            }
            if ($a = $data['assessment'] ?? null) {
                $encounter->assessment()->updateOrCreate([], $a);
            }
            foreach ([['complaints', 'complaints'], ['pain', 'painAssessments'], ['functional', 'functionalEntries'], ['objective', 'objectiveFindings'], ['rom', 'romMeasurements'], ['strength', 'strengthMeasurements'], ['special_tests', 'specialTests'], ['red_flags', 'redFlags'], ['problems', 'problems'], ['body_map', 'bodyMapAnnotations']] as [$key, $rel]) {
                if (array_key_exists($key, $data)) {
                    $encounter->{$rel}()->delete();
                    foreach ($data[$key] as $row) {
                        if ($key === 'red_flags') {
                            $row['recorded_by'] = $request->user()->id;
                        }
                        $encounter->{$rel}()->create($row);
                    }
                }
            }
        });
        Audit::record('clinical.assessment_updated', $encounter->patient, null, ['encounter' => $encounter->encounter_number], $encounter->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Assessment saved.']);
    }

    public function transition(Request $request, ClinicalEncounter $encounter, ClinicalEncounterService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $encounter->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(ClinicalEncounter::STATUSES)], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'finalized' => 'patientcare.clinical.assessments.finalize', 'cancelled' => 'patientcare.clinical.encounters.cancel', 'amended' => 'patientcare.clinical.assessments.amend', default => 'patientcare.clinical.encounters.update' };
        abort_unless($request->user()->can($perm), 403);
        $svc->transition($encounter, $data['status'], $request->user()->id, $data['reason'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => "Encounter {$data['status']}."]);
    }

    public function amend(Request $request, ClinicalEncounter $encounter, ClinicalEncounterService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $encounter->patient), 403);
        abort_unless($request->user()->can('patientcare.clinical.assessments.amend'), 403);
        $data = $request->validate(['reason' => 'required|string|max:500', 'notes' => 'nullable|string']);
        $svc->amend($encounter, ['notes' => $data['notes'] ?? null], $data['reason'], $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Amendment recorded (original preserved).']);
    }

    public function print(Request $request, ClinicalEncounter $encounter, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $encounter->patient), 403);

        return view('clinical::clinical.print', [
            'encounter' => $encounter->load(['patient', 'branch', 'physiotherapist', 'assessment', 'complaints', 'painAssessments', 'functionalEntries', 'objectiveFindings', 'romMeasurements', 'strengthMeasurements', 'specialTests', 'redFlags', 'problems', 'bodyMapAnnotations']),
            'hospital' => \Modules\Foundation\Models\HospitalProfile::first(),
        ]);
    }
}
