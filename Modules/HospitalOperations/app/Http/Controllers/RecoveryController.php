<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\RecoveryArea;
use Modules\HospitalOperations\Models\RecoveryBed;
use Modules\HospitalOperations\Models\SurgicalHandover;
use Modules\HospitalOperations\Models\SurgicalRecoveryRecord;
use Modules\HospitalOperations\Models\Ward;
use Modules\HospitalOperations\Models\Bed;
use Modules\HospitalOperations\Services\SurgicalIntegrationService;
use Modules\HospitalOperations\Services\SurgicalRecoveryService;
use Modules\PatientCare\Services\PatientAccessService;

class RecoveryController extends Controller
{
    private function guardRecord(Request $request, SurgicalRecoveryRecord $r): void
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $r->branch_id), 403);
        abort_unless(app(PatientAccessService::class)->canAccess($request->user(), $r->patient), 403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        return Inertia::render('admin/surgery/recovery', [
            'records' => SurgicalRecoveryRecord::where('branch_id', $branchId)
                ->with(['case:id,case_number,priority', 'patient:id,mrn,first_name,last_name', 'bed:id,code,status', 'assessments' => fn ($q) => $q->latest()->limit(3)])
                ->latest()->paginate(20),
            'areas' => RecoveryArea::where('branch_id', $branchId)->with('beds')->get(),
            'beds' => RecoveryBed::where('branch_id', $branchId)->with('area:id,name')->get(),
            'handovers' => SurgicalHandover::where('branch_id', $branchId)->where('status', 'pending')->with('patient:id,mrn,first_name,last_name', 'case:id,case_number')->latest()->get(),
            'wards' => Ward::where('branch_id', $branchId)->where('active', true)->get(['id', 'name', 'code', 'category']),
            'icuBeds' => app(SurgicalIntegrationService::class)->icuBeds($branchId),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
            'statuses' => SurgicalRecoveryRecord::STATUSES,
        ]);
    }

    public function storeArea(Request $request)
    {
        abort_unless($request->user()->can('surgery.theatres.manage'), 403);
        $data = $request->validate(['code' => 'required|unique:recovery_areas,code', 'name' => 'required|string', 'branch_id' => 'required|exists:branches,id', 'capacity' => 'nullable|integer|min:0']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        RecoveryArea::create($data);
        return back()->with('toast', ['type' => 'success', 'message' => 'Recovery area created.']);
    }

    public function storeBed(Request $request)
    {
        abort_unless($request->user()->can('surgery.theatres.manage'), 403);
        $data = $request->validate(['code' => 'required|unique:recovery_beds,code', 'recovery_area_id' => 'required|exists:recovery_areas,id']);
        $area = RecoveryArea::findOrFail($data['recovery_area_id']);
        RecoveryBed::create($data + ['branch_id' => $area->branch_id, 'status' => 'available']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Recovery bed created.']);
    }

    public function receive(Request $request, SurgicalRecoveryRecord $record, SurgicalRecoveryService $svc)
    {
        $this->guardRecord($request, $record);
        abort_unless($request->user()->can('surgery.recovery.record'), 403);
        $data = $request->validate(['recovery_bed_id' => 'required|exists:recovery_beds,id', 'anesthesia_handover' => 'nullable|string|max:2000']);
        $svc->receive($record, (int) $data['recovery_bed_id'], $data['anesthesia_handover'] ?? null, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Patient received into PACU.']);
    }

    public function assess(Request $request, SurgicalRecoveryRecord $record, SurgicalRecoveryService $svc)
    {
        $this->guardRecord($request, $record);
        abort_unless($request->user()->can('surgery.recovery.record'), 403);
        $data = $request->validate([
            'observations' => 'nullable|string', 'pain_score' => 'nullable|integer|min:0|max:10',
            'consciousness' => 'nullable|string|max:100', 'interventions' => 'nullable|string',
            'adverse_events' => 'nullable|string', 'notes' => 'nullable|string',
            'outcome' => 'nullable|in:progressing,reassessment_required,ready_for_transfer,escalated',
            'disposition_recommendation' => 'nullable|string|max:200',
        ]);
        $svc->addAssessment($record, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Assessment recorded.']);
    }

    public function clear(Request $request, SurgicalRecoveryRecord $record, SurgicalRecoveryService $svc)
    {
        $this->guardRecord($request, $record);
        abort_unless($request->user()->can('surgery.recovery.clear'), 403);
        $data = $request->validate(['disposition' => 'nullable|string|max:200']);
        $svc->clear($record, $data['disposition'] ?? null, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Cleared for transfer.']);
    }

    public function createHandover(Request $request, SurgicalRecoveryRecord $record, SurgicalRecoveryService $svc)
    {
        $this->guardRecord($request, $record);
        abort_unless($request->user()->can('surgery.handover.create'), 403);
        $data = $request->validate([
            'destination_type' => 'required|in:ward,surgical_ward,icu,hdu,recovery,other',
            'destination_ward_id' => 'nullable|exists:wards,id', 'destination_bed_id' => 'nullable|exists:beds,id',
            'admission_id' => 'nullable|exists:admissions,id',
            'operation_summary' => 'nullable|string', 'procedure_performed' => 'nullable|string',
            'anesthesia_summary' => 'nullable|string', 'medications_summary' => 'nullable|string',
            'observations' => 'nullable|string', 'allergies' => 'nullable|string', 'complications' => 'nullable|string',
            'outstanding_investigations' => 'nullable|string', 'precautions' => 'nullable|string', 'pending_actions' => 'nullable|string',
        ]);
        $svc->createHandover($record, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Handover sent; awaiting acknowledgement.']);
    }

    public function acknowledgeHandover(Request $request, SurgicalHandover $handover, SurgicalRecoveryService $svc)
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $handover->branch_id), 403);
        abort_unless($request->user()->can('surgery.handover.acknowledge'), 403);
        $svc->acknowledgeHandover($handover, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Handover acknowledged; transfer complete.']);
    }

    public function postopAdmission(Request $request, \Modules\HospitalOperations\Models\SurgicalCase $case, SurgicalIntegrationService $svc)
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $case->branch_id), 403);
        abort_unless($request->user()->can('surgery.handover.create'), 403);
        $data = $request->validate(['ward_id' => 'required|exists:wards,id', 'bed_id' => 'required|exists:beds,id']);
        $a = $svc->createPostopAdmission($case, (int) $data['ward_id'], (int) $data['bed_id'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Admission {$a->admission_number} created."]);
    }

    public function cleanBed(Request $request, RecoveryBed $bed, SurgicalRecoveryService $svc)
    {
        abort_unless($request->user()->can('surgery.recovery.record'), 403);
        $svc->completeCleaning($bed, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Recovery bed available.']);
    }
}
