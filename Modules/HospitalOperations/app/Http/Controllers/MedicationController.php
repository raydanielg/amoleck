<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\MedicationAdministration;
use Modules\HospitalOperations\Models\MedicationCatalogue;
use Modules\HospitalOperations\Models\MedicationOrder;
use Modules\HospitalOperations\Models\MedicationScheduledDose;
use Modules\HospitalOperations\Services\MedicationService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class MedicationController extends Controller
{
    public function emar(Request $request, MedicationService $svc)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        return Inertia::render('admin/emar/index', [
            'dashboard' => $svc->emarDashboard($branchId),
            'orders' => MedicationOrder::where('branch_id', $branchId)->whereIn('status', ['active','approved','pending_review'])->with('patient:id,mrn,first_name,last_name', 'createdBy:id,name')->latest()->paginate(20),
            'dueDoses' => MedicationScheduledDose::whereHas('order', fn ($q) => $q->where('branch_id', $branchId))->where('scheduled_at', '<=', now()->addMinutes(30))->where('status', 'scheduled')->with('order:id,generic_name,dose,route', 'patient:id,mrn,first_name,last_name')->orderBy('scheduled_at')->limit(50)->get(),
            'catalogue' => MedicationCatalogue::where('active', true)->get(['id','generic_name','brand_name','dosage_form','strength']),
            'admissions' => Admission::where('branch_id', $branchId)->where('status', 'admitted')->with('patient:id,mrn,first_name,last_name')->get(['id','admission_number','patient_id','branch_id','ward_id']),
            'types' => ['regular','once','prn','stat','infusion'],
            'routes' => ['oral','iv','im','sc','topical','inhaled','pr','sl'],
        ]);
    }

    public function storeOrder(Request $request, MedicationService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'admission_id' => 'required|exists:admissions,id',
            'medication_catalogue_id' => 'nullable|exists:medication_catalogue,id',
            'generic_name' => 'required|string|max:200', 'brand_name' => 'nullable',
            'dose' => 'required|string|max:50', 'dose_unit' => 'required|string|max:20', 'route' => 'required|string',
            'frequency' => 'required|string', 'schedule_times' => 'nullable|array', 'schedule_times.*' => 'date_format:H:i',
            'type' => ['required', Rule::in(['regular','once','prn','stat','infusion'])],
            'prn_min_interval_minutes' => 'nullable|integer|min:0', 'instructions' => 'nullable', 'indication' => 'nullable',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $adm = Admission::findOrFail($data['admission_id']);
        abort_unless($adm->patient_id === $patient->id && $adm->status === 'admitted', 422);
        $data['branch_id'] = $adm->branch_id;
        $data['ward_id'] = $adm->ward_id;
        $o = $svc->createOrder($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Order {$o->order_number}"]);
    }

    public function transition(Request $request, MedicationOrder $order, MedicationService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $order->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(MedicationService::TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'approved' => 'medications.orders.approve', 'discontinued' => 'medications.orders.modify', 'pending_review' => 'medications.orders.create', default => 'medications.orders.modify' };
        abort_unless($request->user()->can($perm), 403);
        abort_if($data['status'] === 'approved' && $order->created_by === $request->user()->id && ! $request->user()->hasRole('Super Admin'), 422, 'Cannot approve own order.');
        $svc->transition($order, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Order {$data['status']}"]);
    }

    public function generateDoses(Request $request, MedicationOrder $order, MedicationService $svc)
    {
        abort_unless($request->user()->can('medications.orders.modify'), 403);
        $n = $svc->generateDoses($order, $request->integer('days', 3));
        return back()->with('toast', ['type' => 'success', 'message' => "{$n} doses scheduled."]);
    }

    public function administer(Request $request, MedicationScheduledDose $dose, MedicationService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $dose->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('medications.administration.record'), 403);
        $data = $request->validate(['actual_dose' => 'required|string|max:50', 'actual_route' => 'required|string', 'notes' => 'nullable']);
        $svc->administer($dose, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Administered.']);
    }

    public function recordNonAdmin(Request $request, MedicationScheduledDose $dose, MedicationService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $dose->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('medications.administration.record'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(MedicationService::NON_ADMIN)], 'reason' => 'required|string|max:500']);
        $svc->recordNonAdmin($dose, $data['status'], $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Dose marked {$data['status']}."]);
    }

    public function administerPrn(Request $request, MedicationOrder $order, MedicationService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $order->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('medications.prn.manage'), 403);
        $data = $request->validate(['actual_dose' => 'required|string|max:50', 'actual_route' => 'required|string', 'reason' => 'required|string|max:500', 'patient_response' => 'nullable', 'notes' => 'nullable']);
        $svc->administerPrn($order, $data, $request->user()->id, $data['reason']);
        return back()->with('toast', ['type' => 'success', 'message' => 'PRN administered.']);
    }

    public function storeCatalogue(Request $request)
    {
        abort_unless($request->user()->can('medications.catalogue.manage'), 403);
        MedicationCatalogue::create($request->validate(['code' => 'required|unique:medication_catalogues', 'generic_name' => 'required', 'brand_name' => 'nullable', 'category' => 'nullable', 'dosage_form' => 'required', 'strength' => 'nullable', 'strength_unit' => 'nullable', 'routes' => 'nullable|array', 'controlled' => 'boolean']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Medication added.']);
    }

    public function history(Request $request, Patient $patient, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        return response()->json([
            'orders' => MedicationOrder::where('patient_id', $patient->id)->with('createdBy:id,name')->latest()->get(),
            'administrations' => MedicationAdministration::where('patient_id', $patient->id)->with('administeredBy:id,name', 'order:id,generic_name,dose')->latest('administered_at')->get(),
        ]);
    }
}
