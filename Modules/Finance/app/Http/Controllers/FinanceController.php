<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\PatientCharge;
use Modules\Finance\Models\ServicePrice;
use Modules\Finance\Services\FinanceService;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class FinanceController extends Controller
{
    public function index(Request $request, FinanceService $svc)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        return Inertia::render('admin/finance/index', [
            'dashboard' => $svc->dashboard($branchId),
            'charges' => PatientCharge::where('branch_id', $branchId)->with('patient:id,mrn,first_name,last_name', 'service:id,name')->latest()->paginate(20),
            'prices' => ServicePrice::where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))->with('service:id,name')->latest()->paginate(15),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'services' => ClinicalService::where('status', 'active')->get(['id', 'name', 'code', 'department_id']),
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->limit(50)->get(),
        ]);
    }

    public function storePrice(Request $request)
    {
        abort_unless($request->user()->can('finance.pricing.manage'), 403);
        $data = $request->validate([
            'clinical_service_id' => 'required|exists:clinical_services,id', 'service_name' => 'required|string|max:200',
            'department_id' => 'nullable|exists:departments,id', 'category' => 'nullable|string',
            'price' => 'required|numeric|min:0|max:99999999', 'branch_id' => 'nullable|exists:branches,id',
            'effective_from' => 'nullable|date', 'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);
        ServicePrice::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Price created.']);
    }

    public function transitionPrice(Request $request, ServicePrice $price, FinanceService $svc)
    {
        abort_unless($request->user()->can('finance.pricing.approve'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(FinanceService::PRICE_TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $svc->transitionPrice($price, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Price {$data['status']}."]);
    }

    public function storeCharge(Request $request, FinanceService $svc, PatientAccessService $access)
    {
        abort_unless($request->user()->can('finance.charges.manage'), 403);
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'clinical_service_id' => 'required|exists:clinical_services,id',
            'source' => 'required|string|max:50', 'source_id' => 'nullable|integer',
            'service_date' => 'required|date', 'quantity' => 'nullable|integer|min:1|max:100',
            'discount' => 'nullable|numeric|min:0', 'description' => 'nullable|string|max:500',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $c = $svc->createCharge($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Charge {$c->charge_number}"]);
    }

    public function transitionCharge(Request $request, PatientCharge $charge, FinanceService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $charge->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(FinanceService::CHARGE_TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'approved' => 'finance.charges.post', 'posted' => 'finance.charges.post', 'reversed', 'partially_reversed' => 'finance.charges.reverse', default => 'finance.charges.manage' };
        abort_unless($request->user()->can($perm), 403);
        $svc->transitionCharge($charge, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Charge {$data['status']}"]);
    }

    public function reverseCharge(Request $request, PatientCharge $charge, FinanceService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $charge->patient) && $access->canAccess($request->user(), $p), 403);
        abort_unless($request->user()->can('finance.charges.reverse'), 403);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $svc->reverseCharge($charge, $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Reversed.']);
    }

    public function patientSummary(Request $request, Patient $patient, FinanceService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless($request->user()->can('finance.patient_financials.view'), 403);
        $branchId = $request->integer('branch_id') ?: $patient->primary_branch_id;
        return response()->json($svc->patientSummary($patient->id, $branchId));
    }
}
