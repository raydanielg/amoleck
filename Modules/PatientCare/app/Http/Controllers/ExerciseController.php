<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Clinical\Models\ClinicalExercise;
use Modules\PatientCare\Clinical\Models\ClinicalExercisePrescription;
use Modules\PatientCare\Clinical\Models\ClinicalHomeExerciseProgram;
use Modules\PatientCare\Clinical\Models\ClinicalPrescribedExercise;
use Modules\PatientCare\Clinical\Models\ClinicalExerciseAdherenceRecord;
use Modules\PatientCare\Clinical\Services\ExerciseProgramService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class ExerciseController extends Controller
{
    public function library()
    {
        return Inertia::render('admin/exercises/index', ['exercises' => ClinicalExercise::orderBy('category')->orderBy('name')->paginate(20), 'categories' => ClinicalExercise::CATEGORIES]);
    }

    public function storeExercise(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'category' => 'required|string', 'description' => 'nullable|string', 'instructions_en' => 'nullable|string', 'instructions_sw' => 'nullable|string', 'contraindications' => 'nullable|string', 'difficulty' => 'nullable|string']);
        ClinicalExercise::create($data + ['code' => 'EX-'.str_pad((string) (ClinicalExercise::max('id') + 1), 5, '0', STR_PAD_LEFT)]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Exercise added.']);
    }

    public function uploadMedia(Request $request, ClinicalExercise $exercise)
    {
        $data = $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf,mp4|max:20480', 'instruction' => 'nullable|string|max:255', 'step_number' => 'nullable|integer']);
        $path = $request->file('file')->store("exercises/{$exercise->id}");
        $exercise->media()->create(['path' => $path, 'original_name' => $request->file('file')->getClientOriginalName(), 'mime' => $request->file('file')->getMimeType(), 'size' => $request->file('file')->getSize(), 'uploaded_by' => $request->user()->id, 'instruction' => $data['instruction'] ?? null, 'step_number' => $data['step_number'] ?? null]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Media uploaded.']);
    }

    public function storePrescription(Request $request, Patient $patient, ExerciseProgramService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['clinical_treatment_plan_id' => 'nullable|exists:clinical_treatment_plans,id', 'branch_id' => 'required|exists:branches,id', 'instructions' => 'nullable|string', 'start_on' => 'nullable|date', 'review_on' => 'nullable|date']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $rx = ClinicalExercisePrescription::create($data + ['patient_id' => $patient->id, 'rx_number' => $svc->nextNumber('RX'), 'prescribed_on' => now(), 'physiotherapist_id' => $request->user()->id, 'created_by' => $request->user()->id]);
        Audit::record('exercise_program.created', $patient, null, ['rx' => $rx->rx_number], $rx->branch_id);
        return redirect()->route('admin.exercise-programs.show', ['program' => $rx->id, 'kind' => 'rx']);
    }

    public function storeProgram(Request $request, Patient $patient, ExerciseProgramService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate(['clinical_treatment_plan_id' => 'nullable|exists:clinical_treatment_plans,id', 'branch_id' => 'required|exists:branches,id', 'general_instructions' => 'nullable|string', 'emergency_instructions' => 'nullable|string', 'start_on' => 'nullable|date', 'review_on' => 'nullable|date']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $p = ClinicalHomeExerciseProgram::create($data + ['patient_id' => $patient->id, 'hep_number' => $svc->nextNumber('HEP'), 'issued_on' => now(), 'physiotherapist_id' => $request->user()->id, 'created_by' => $request->user()->id]);
        Audit::record('exercise_program.created', $patient, null, ['hep' => $p->hep_number], $p->branch_id);
        return redirect()->route('admin.exercise-programs.show', ['program' => $p->id, 'kind' => 'hep']);
    }

    public function show(Request $request, int $program, ExerciseProgramService $svc, PatientAccessService $access)
    {
        $kind = $request->input('kind', 'hep');
        $e = $kind === 'rx' ? ClinicalExercisePrescription::findOrFail($program) : ClinicalHomeExerciseProgram::findOrFail($program);
        abort_unless($access->canAccess($request->user(), $e->patient), 403);

        return Inertia::render('admin/exercise-programs/show', [
            'entity' => $e->load(['patient:id,mrn,first_name,last_name', 'physiotherapist:id,name', 'exercises.exercise:id,name,code', 'plan:id,plan_number']),
            'kind' => $kind,
            'adherence' => $kind === 'hep' ? $svc->adherenceRate($e->load('adherence')) : null,
            'adherenceRecords' => $kind === 'hep' ? $e->adherence()->latest('performed_on')->limit(20)->get() : [],
            'library' => ClinicalExercise::where('active', true)->get(['id', 'name', 'code', 'category']),
            'versions' => \Modules\PatientCare\Clinical\Models\ClinicalExerciseVersion::where('entity_type', get_class($e))->where('entity_id', $e->id)->latest('version')->get(),
            'safety' => ['allergies' => $e->patient->medicalEntries()->where('type', 'allergy')->pluck('description'), 'conditions' => $e->patient->conditions()->pluck('name')],
        ]);
    }

    public function update(Request $request, int $program, PatientAccessService $access, ExerciseProgramService $svc)
    {
        $kind = $request->input('kind', 'hep');
        $e = $kind === 'rx' ? ClinicalExercisePrescription::findOrFail($program) : ClinicalHomeExerciseProgram::findOrFail($program);
        abort_unless($access->canAccess($request->user(), $e->patient), 403);
        abort_if(in_array($e->status, ['completed', 'discontinued', 'cancelled']), 422, 'Closed program.');

        DB::transaction(function () use ($e, $request) {
            if ($request->has('entity')) {
                $e->update(array_filter($request->input('entity'), fn ($v) => $v !== null));
            }
            if ($request->has('exercises')) {
                $e->exercises()->delete();
                foreach ($request->input('exercises', []) as $row) {
                    if (empty($row['clinical_exercise_id']) && empty($row['custom_name'])) continue;
                    $e->exercises()->create($row);
                }
            }
        });
        Audit::record('exercise_program.updated', $e->patient, null, ['entity' => $e->hep_number ?? $e->rx_number], $e->branch_id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Saved.']);
    }

    public function transition(Request $request, int $program, ExerciseProgramService $svc, PatientAccessService $access)
    {
        $kind = $request->input('kind', 'hep');
        $e = $kind === 'rx' ? ClinicalExercisePrescription::findOrFail($program) : ClinicalHomeExerciseProgram::findOrFail($program);
        abort_unless($access->canAccess($request->user(), $e->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(ClinicalHomeExerciseProgram::STATUSES)], 'reason' => 'nullable|string|max:500']);
        $perm = $data['status'] === 'approved' ? 'patientcare.clinical.exercises.prescriptions.approve' : 'patientcare.clinical.home_programs.manage';
        abort_unless($request->user()->can($perm), 403);
        $svc->transition($e, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Updated."]);
    }

    public function recordAdherence(Request $request, ClinicalHomeExerciseProgram $program, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $program->patient), 403);
        $data = $request->validate([
            'clinical_prescribed_exercise_id' => 'nullable|exists:clinical_prescribed_exercises,id',
            'performed_on' => 'required|date',
            'status' => ['required', Rule::in(['completed', 'partially_completed', 'not_completed', 'skipped', 'not_reported'])],
            'completed_sets' => 'nullable|integer', 'completed_reps' => 'nullable|integer', 'actual_duration' => 'nullable|string',
            'difficulty' => 'nullable|string', 'pain' => 'nullable|string', 'non_completion_reason' => 'nullable|string',
            'comments' => 'nullable|string', 'source' => ['required', Rule::in(['patient_reported', 'clinician_observed'])],
        ]);
        $program->adherence()->create($data + ['recorded_by' => $request->user()->id]);
        Audit::record('exercise_program.adherence', $program->patient, null, ['hep' => $program->hep_number], $program->branch_id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Adherence recorded.']);
    }

    public function print(Request $request, ClinicalHomeExerciseProgram $program, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $program->patient), 403);
        return view('clinical::clinical.hep-print', ['program' => $program->load(['patient', 'physiotherapist', 'exercises.exercise']), 'hospital' => \Modules\Foundation\Models\HospitalProfile::first()]);
    }
}
