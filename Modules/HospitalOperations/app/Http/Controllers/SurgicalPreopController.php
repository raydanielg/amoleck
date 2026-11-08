<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\AnesthesiaPreassessment;
use Modules\HospitalOperations\Models\PreoperativeAssessment;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalChecklistItem;
use Modules\HospitalOperations\Models\SurgicalConsent;
use Modules\HospitalOperations\Services\SurgicalIntegrationService;
use Modules\HospitalOperations\Services\SurgicalPreopService;
use Modules\PatientCare\Services\PatientAccessService;

class SurgicalPreopController extends Controller
{
    private function guardCase(Request $request, SurgicalCase $case): void
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $case->branch_id), 403);
        abort_unless(app(PatientAccessService::class)->canAccess($request->user(), $case->patient), 403);
    }

    public function saveAssessment(Request $request, SurgicalCase $case, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.preoperative.assess'), 403);
        $data = $request->validate([
            'medical_history' => 'nullable|string', 'previous_surgery' => 'nullable|string',
            'current_medications' => 'nullable|string', 'allergies_summary' => 'nullable|string',
            'anesthesia_history' => 'nullable|string', 'family_social_history' => 'nullable|string',
            'clinical_condition' => 'nullable|string', 'examination_findings' => 'nullable|string',
            'functional_status' => 'nullable|string', 'precautions' => 'nullable|string',
            'investigations_summary' => 'nullable|string', 'outstanding_investigations' => 'nullable|string',
            'clinical_notes' => 'nullable|string', 'outstanding_actions' => 'nullable|string',
            'readiness_recommendation' => 'nullable|string', 'status' => 'nullable|in:in_progress,pending_review',
        ]);
        $svc->saveAssessment($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Pre-operative assessment saved.']);
    }

    public function reviewAssessment(Request $request, PreoperativeAssessment $assessment, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $assessment->case);
        abort_unless($request->user()->can('surgery.preoperative.approve'), 403);
        $data = $request->validate(['status' => 'required|in:cleared,cleared_with_conditions,not_cleared,requires_further_assessment', 'notes' => 'nullable|string|max:1000']);
        $svc->reviewAssessment($assessment, $data['status'], $request->user()->id, $data['notes'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Assessment {$data['status']}."]);
    }

    public function saveAnesthesia(Request $request, SurgicalCase $case, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.anesthesia.assess'), 403);
        $data = $request->validate([
            'medical_history' => 'nullable|string', 'previous_anesthesia' => 'nullable|string',
            'allergies' => 'nullable|string', 'current_medications' => 'nullable|string',
            'examination' => 'nullable|string', 'investigations' => 'nullable|string',
            'airway_assessment' => 'nullable|string', 'considerations' => 'nullable|string',
            'proposed_technique' => 'nullable|in:general,regional,local,sedation,other',
            'clinical_notes' => 'nullable|string', 'status' => 'nullable|in:in_progress,pending_review',
        ]);
        $svc->saveAnesthesiaAssessment($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Anesthesia assessment saved.']);
    }

    public function reviewAnesthesia(Request $request, AnesthesiaPreassessment $assessment, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $assessment->case);
        abort_unless($request->user()->can('surgery.anesthesia.approve'), 403);
        $data = $request->validate(['status' => 'required|in:approved,not_approved,requires_further_assessment']);
        $svc->reviewAnesthesiaAssessment($assessment, $data['status'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Anesthesia assessment {$data['status']}."]);
    }

    public function recordConsent(Request $request, SurgicalCase $case, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.consent.record'), 403);
        $data = $request->validate([
            'type' => 'required|in:procedure,anesthesia,blood_product,additional,other',
            'status' => 'required|in:pending,consented,declined,requires_reconfirmation,not_applicable',
            'decision' => 'nullable|in:consented,declined',
            'clinician_id' => 'nullable|exists:users,id',
            'information_explained' => 'nullable|string', 'risks_alternatives' => 'nullable|string',
            'representative_name' => 'nullable|string', 'representative_relationship' => 'nullable|string',
            'witness_name' => 'nullable|string', 'interpreter_name' => 'nullable|string',
            'status_reason' => 'nullable|string', 'document_path' => 'nullable|string',
        ]);
        $svc->recordConsent($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Consent recorded.']);
    }

    public function withdrawConsent(Request $request, SurgicalConsent $consent, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $consent->case);
        abort_unless($request->user()->can('surgery.consent.withdraw'), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $svc->withdrawConsent($consent, $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Consent withdrawn.']);
    }

    public function initChecklist(Request $request, SurgicalCase $case, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.preoperative.assess'), 403);
        $data = $request->validate(['stage' => 'required|in:preparation,sign_in,time_out,sign_out']);
        $n = $svc->instantiateChecklist($case, $data['stage'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "{$n} checklist items created."]);
    }

    public function completeItem(Request $request, SurgicalChecklistItem $item, SurgicalPreopService $svc)
    {
        $this->guardCase($request, $item->case);
        abort_unless($request->user()->can('surgery.safety_checklist.complete') || $request->user()->can('surgery.preoperative.assess'), 403);
        $data = $request->validate(['status' => 'required|in:pending,completed,not_applicable,blocked,requires_review', 'evidence' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:1000']);
        $svc->completeItem($item, $data, $request->user()->id, $request->user()->roles->pluck('name')->all());
        return back()->with('toast', ['type' => 'success', 'message' => 'Item updated.']);
    }

    public function requestBlood(Request $request, SurgicalCase $case, SurgicalIntegrationService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.cases.update'), 403);
        $data = $request->validate(['product_type' => 'required|string|max:100', 'units' => 'required|integer|min:1|max:50', 'notes' => 'nullable|string|max:1000']);
        $svc->requestBloodRequirement($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Blood requirement recorded (Phase 12 boundary).']);
    }

    public function confirmBlood(Request $request, \Modules\HospitalOperations\Models\SurgicalBloodRequirement $requirement, SurgicalIntegrationService $svc)
    {
        $this->guardCase($request, $requirement->case);
        abort_unless($request->user()->can('surgery.cases.approve'), 403);
        $data = $request->validate(['status' => 'required|in:confirmed,unavailable,cancelled']);
        $svc->confirmBloodRequirement($requirement, $data['status'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Blood requirement {$data['status']}."]);
    }
}
