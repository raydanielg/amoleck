<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\AnesthesiaRecord;
use Modules\HospitalOperations\Models\OperativeNote;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalChecklistTemplate;
use Modules\HospitalOperations\Models\SurgicalCount;
use Modules\HospitalOperations\Models\SurgicalIncident;
use Modules\HospitalOperations\Models\SurgicalOperation;
use Modules\HospitalOperations\Models\SurgicalSpecimen;
use Modules\HospitalOperations\Services\SurgicalMaterialService;
use Modules\HospitalOperations\Services\SurgicalOperationService;
use Modules\HospitalOperations\Services\SurgicalSafetyService;
use Modules\Inventory\Models\InventoryBatch;
use Modules\PatientCare\Services\PatientAccessService;

class SurgicalOperationController extends Controller
{
    private function guardCase(Request $request, SurgicalCase $case): void
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $case->branch_id), 403);
        abort_unless(app(PatientAccessService::class)->canAccess($request->user(), $case->patient), 403);
    }

    private function guardOp(Request $request, SurgicalOperation $op): void
    {
        $this->guardCase($request, $op->case);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        return Inertia::render('admin/surgery/operations/index', [
            'operations' => SurgicalOperation::where('branch_id', $branchId)
                ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
                ->with(['case:id,case_number,priority', 'patient:id,mrn,first_name,last_name', 'theatre:id,code,name', 'surgeon:id,name'])
                ->latest()->paginate(20),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
        ]);
    }

    public function show(Request $request, SurgicalOperation $operation)
    {
        $this->guardOp($request, $operation);
        $operation->load([
            'case:id,case_number,status,priority,surgical_procedure_id,patient_id,branch_id',
            'case.patient:id,mrn,first_name,last_name,gender,date_of_birth',
            'case.safetyVerifications.items', 'case.safetyExceptions', 'case.consents',
            'case.teamAssignments.user:id,name', 'procedures.procedure:id,name', 'operativeNote.versions',
            'anesthesiaRecord.medicationRefs.administration', 'counts.items', 'specimens.handovers', 'implants', 'consumables.item', 'consumables.batch',
            'theatre:id,code,name', 'surgeon:id,name', 'anesthetist:id,name',
        ]);
        return Inertia::render('admin/surgery/operations/show', [
            'operation' => $operation,
            'templates' => [
                'sign_in' => SurgicalChecklistTemplate::where('stage', 'sign_in')->where('active', true)->latest('version')->first()?->items ?? [],
                'time_out' => SurgicalChecklistTemplate::where('stage', 'time_out')->where('active', true)->latest('version')->first()?->items ?? [],
                'sign_out' => SurgicalChecklistTemplate::where('stage', 'sign_out')->where('active', true)->latest('version')->first()?->items ?? [],
            ],
            'batches' => InventoryBatch::where('status', 'available')->where('qty', '>', 0)->with('item:id,name,code')->limit(100)->get(['id', 'inventory_item_id', 'warehouse_id', 'batch_number', 'expiry_date', 'qty']),
            'medications' => \Modules\HospitalOperations\Models\MedicationAdministration::where('patient_id', $operation->patient_id)->latest()->limit(50)->get(['id', 'medication_order_id', 'actual_dose', 'actual_route', 'administered_at', 'administered_by']),
        ]);
    }

    // ---- Safety ----
    public function verifySafety(Request $request, SurgicalCase $case, SurgicalSafetyService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.safety_checklist.complete'), 403);
        $data = $request->validate([
            'stage' => 'required|in:sign_in,time_out,sign_out',
            'items' => 'required|array', 'items.*.key' => 'required|string',
            'items.*.checked' => 'nullable|boolean', 'items.*.value' => 'nullable|string|max:500', 'items.*.notes' => 'nullable|string|max:500',
            'participant_ids' => 'nullable|array', 'participant_ids.*' => 'exists:users,id',
            'notes' => 'nullable|string|max:1000',
        ]);
        $svc->verify($case, $data['stage'], $data['items'], $request->user()->id, $data['participant_ids'] ?? [], $data['notes'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => ucfirst(str_replace('_', ' ', $data['stage'])).' verified.']);
    }

    public function safetyException(Request $request, SurgicalCase $case, SurgicalSafetyService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.safety_checklist.complete'), 403);
        $data = $request->validate([
            'stage' => 'required|in:sign_in,time_out,sign_out', 'item_key' => 'required|string|max:100',
            'reason' => 'required|string|max:1000', 'authorized_by' => 'required|exists:users,id',
        ]);
        abort_unless($request->user()->id === (int) $data['authorized_by'] || $request->user()->can('surgery.cases.approve'), 403, 'Exception must be authorized.');
        $svc->recordException($case, $data['stage'], $data['item_key'], $data['reason'], (int) $data['authorized_by'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Safety exception recorded.']);
    }

    // ---- Operation lifecycle ----
    public function start(Request $request, SurgicalCase $case, SurgicalOperationService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.operation.start'), 403);
        $data = $request->validate(['surgical_booking_id' => 'nullable|exists:surgical_bookings,id']);
        $op = $svc->start($case, $request->user()->id, $data['surgical_booking_id'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Operation {$op->operation_number} started."]);
    }

    public function recordProcedure(Request $request, SurgicalOperation $operation, SurgicalOperationService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.operation.document'), 403);
        $data = $request->validate([
            'surgical_procedure_id' => 'nullable|exists:surgical_procedures,id', 'procedure_name' => 'required|string|max:300',
            'kind' => 'required|in:planned,performed,additional,changed,aborted,incomplete',
            'surgeon_id' => 'nullable|exists:users,id', 'notes' => 'nullable|string|max:2000',
        ]);
        $svc->recordProcedure($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Procedure recorded.']);
    }

    public function complete(Request $request, SurgicalOperation $operation, SurgicalOperationService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.operation.document'), 403);
        $data = $request->validate([
            'preop_diagnosis' => 'nullable|string', 'postop_diagnosis' => 'nullable|string',
            'findings' => 'nullable|string', 'narrative' => 'nullable|string', 'complications' => 'nullable|string',
            'unexpected_events' => 'nullable|string', 'estimated_blood_loss_ml' => 'nullable|integer|min:0',
            'outcome' => 'nullable|string|max:300', 'postoperative_destination' => 'nullable|in:pacu,ward,surgical_ward,icu,hdu,home,other',
            'anesthesia_end_at' => 'nullable|date', 'procedure_end_at' => 'nullable|date', 'patient_out_at' => 'nullable|date',
        ]);
        $svc->complete($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Operation completed; theatre sent to cleaning.']);
    }

    public function abortOp(Request $request, SurgicalOperation $operation, SurgicalOperationService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.operation.document'), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $svc->abort($operation, $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Operation aborted.']);
    }

    // ---- Operative notes ----
    public function saveNote(Request $request, SurgicalOperation $operation, SurgicalOperationService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.operation.document'), 403);
        $data = $request->validate([
            'preop_diagnosis' => 'nullable|string', 'postop_diagnosis' => 'nullable|string',
            'findings' => 'nullable|string', 'technique' => 'nullable|string',
            'narrative' => 'nullable|string', 'instructions' => 'nullable|string',
            'status' => 'nullable|in:draft,in_progress,pending_review',
        ]);
        $svc->saveNote($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Operative note saved.']);
    }

    public function transitionNote(Request $request, OperativeNote $note, SurgicalOperationService $svc)
    {
        $this->guardCase($request, $note->case);
        $data = $request->validate(['status' => 'required|in:in_progress,pending_review,finalized']);
        $perm = $data['status'] === 'finalized' ? 'surgery.operation.finalize' : 'surgery.operation.document';
        abort_unless($request->user()->can($perm), 403);
        $svc->transitionNote($note, $data['status'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Note {$data['status']}."]);
    }

    public function amendNote(Request $request, OperativeNote $note, SurgicalOperationService $svc)
    {
        $this->guardCase($request, $note->case);
        abort_unless($request->user()->can('surgery.operation.amend'), 403);
        $data = $request->validate([
            'reason' => 'required|string|max:1000', 'findings' => 'nullable|string', 'narrative' => 'nullable|string',
            'postop_diagnosis' => 'nullable|string', 'technique' => 'nullable|string', 'instructions' => 'nullable|string',
        ]);
        $svc->amendNote($note, $data, $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Note amended; prior version preserved.']);
    }

    // ---- Anesthesia ----
    public function saveAnesthesia(Request $request, SurgicalOperation $operation, SurgicalOperationService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.anesthesia.document'), 403);
        $data = $request->validate([
            'anesthetist_id' => 'nullable|exists:users,id', 'technique' => 'nullable|in:general,regional,local,sedation,other',
            'started_at' => 'nullable|date', 'ended_at' => 'nullable|date', 'observations' => 'nullable|string',
            'airway_management' => 'nullable|string', 'fluids' => 'nullable|string', 'adverse_events' => 'nullable|string',
            'interventions' => 'nullable|string', 'clinical_notes' => 'nullable|string', 'handover_notes' => 'nullable|string',
        ]);
        $svc->saveAnesthesiaRecord($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Anesthesia record saved.']);
    }

    public function linkMedication(Request $request, AnesthesiaRecord $record, SurgicalOperationService $svc)
    {
        $this->guardCase($request, $record->operation->case);
        abort_unless($request->user()->can('surgery.anesthesia.document'), 403);
        $data = $request->validate(['medication_administration_id' => 'required|exists:medication_administrations,id', 'notes' => 'nullable|string|max:500']);
        $svc->linkMedication($record, (int) $data['medication_administration_id'], $data['notes'] ?? null, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'eMAR administration linked.']);
    }

    public function closeAnesthesia(Request $request, AnesthesiaRecord $record, SurgicalOperationService $svc)
    {
        $this->guardCase($request, $record->operation->case);
        abort_unless($request->user()->can('surgery.anesthesia.document'), 403);
        $svc->closeAnesthesiaRecord($record, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Anesthesia record closed.']);
    }

    // ---- Materials ----
    public function recordCount(Request $request, SurgicalOperation $operation, SurgicalMaterialService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.instrument_count.manage'), 403);
        $data = $request->validate([
            'type' => 'required|in:instrument,swab,needle,other', 'phase' => 'required|in:initial,additional,final',
            'items' => 'required|array|min:1', 'items.*.item_name' => 'required|string',
            'items.*.expected_qty' => 'required|integer|min:0', 'items.*.counted_qty' => 'required|integer|min:0',
            'discrepancy_notes' => 'nullable|string|max:1000', 'verified_by' => 'nullable|exists:users,id',
        ]);
        $svc->recordCount($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Count recorded.']);
    }

    public function resolveCount(Request $request, SurgicalCount $count, SurgicalMaterialService $svc)
    {
        $this->guardOp($request, $count->operation);
        abort_unless($request->user()->can('surgery.instrument_count.manage'), 403);
        $data = $request->validate(['resolution' => 'required|string|max:1000']);
        $svc->resolveCount($count, $data['resolution'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Discrepancy resolved.']);
    }

    public function registerSpecimen(Request $request, SurgicalOperation $operation, SurgicalMaterialService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.specimens.manage'), 403);
        $data = $request->validate([
            'description' => 'required|string|max:500', 'anatomical_source' => 'nullable|string|max:200',
            'laterality' => 'nullable|in:left,right,bilateral,midline,na', 'container' => 'nullable|string|max:100',
            'label' => 'nullable|string|max:100', 'destination' => 'nullable|in:laboratory,pathology,other',
            'laboratory_order_id' => 'nullable|exists:laboratory_orders,id',
        ]);
        $s = $svc->registerSpecimen($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Specimen {$s->specimen_number} registered."]);
    }

    public function dispatchSpecimen(Request $request, SurgicalSpecimen $specimen, SurgicalMaterialService $svc)
    {
        $this->guardOp($request, $specimen->operation);
        abort_unless($request->user()->can('surgery.specimens.manage'), 403);
        $data = $request->validate(['received_by_name' => 'nullable|string|max:200', 'notes' => 'nullable|string|max:500', 'laboratory_order_id' => 'nullable|exists:laboratory_orders,id']);
        $svc->dispatchSpecimen($specimen, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Specimen dispatched.']);
    }

    public function receiveSpecimen(Request $request, SurgicalSpecimen $specimen, SurgicalMaterialService $svc)
    {
        abort_unless($request->user()->can('surgery.specimens.manage'), 403);
        $svc->receiveSpecimen($specimen, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Specimen received.']);
    }

    public function recordImplant(Request $request, SurgicalOperation $operation, SurgicalMaterialService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.implants.manage'), 403);
        $data = $request->validate([
            'inventory_item_id' => 'nullable|exists:inventory_items,id', 'item_name' => 'required|string|max:300',
            'batch_number' => 'nullable|string|max:100', 'serial_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date', 'manufacturer' => 'nullable|string|max:200', 'supplier' => 'nullable|string|max:200',
            'quantity' => 'nullable|integer|min:1',
        ]);
        $svc->recordImplant($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Implant recorded.']);
    }

    public function recordConsumable(Request $request, SurgicalOperation $operation, SurgicalMaterialService $svc)
    {
        $this->guardOp($request, $operation);
        abort_unless($request->user()->can('surgery.implants.manage'), 403);
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id', 'inventory_batch_id' => 'required|exists:inventory_batches,id',
            'qty_used' => 'required|numeric|min:0.001', 'qty_returned' => 'nullable|numeric|min:0', 'qty_wasted' => 'nullable|numeric|min:0',
        ]);
        $svc->recordConsumable($operation, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Consumable issued via stock ledger.']);
    }

    // ---- Incidents ----
    public function recordIncident(Request $request, SurgicalCase $case)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.operation.document'), 403);
        $data = $request->validate([
            'category' => 'required|in:intraop_complication,anesthesia_event,postop_complication,equipment,count_discrepancy,medication,other',
            'event_at' => 'required|date', 'description' => 'required|string|max:2000',
            'immediate_actions' => 'nullable|string|max:2000', 'severity' => 'nullable|in:low,medium,high,critical',
            'escalation' => 'nullable|string|max:1000', 'surgical_operation_id' => 'nullable|exists:surgical_operations,id',
        ]);
        SurgicalIncident::create($data + ['surgical_case_id' => $case->id, 'patient_id' => $case->patient_id, 'branch_id' => $case->branch_id, 'reported_by' => $request->user()->id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Incident documented.']);
    }
}
