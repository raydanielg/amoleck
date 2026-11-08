<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Finance\Models\CorporateClient;
use Modules\Finance\Models\CorporateSponsorship;
use Modules\Finance\Models\InsuranceClaim;
use Modules\Finance\Models\InsurancePlan;
use Modules\Finance\Models\InsurancePreauthorization;
use Modules\Finance\Models\InsuranceProvider;
use Modules\Finance\Models\PatientInsuranceMembership;
use Modules\Finance\Services\InsuranceService;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class InsuranceController extends Controller
{
    public function index(Request $request, InsuranceService $svc)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        return Inertia::render('admin/finance/insurance', [
            'dashboard' => $svc->dashboard($branchId),
            'providers' => InsuranceProvider::withCount('plans')->get(),
            'claims' => InsuranceClaim::where('branch_id', $branchId)->with('patient:id,mrn,first_name,last_name', 'invoice:id,invoice_number', 'membership.plan:id,name')->latest()->paginate(20),
            'memberships' => PatientInsuranceMembership::with('patient:id,mrn,first_name,last_name', 'provider:id,name', 'plan:id,name')->latest()->limit(20)->get(),
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->limit(50)->get(),
        ]);
    }

    public function storeProvider(Request $request)
    {
        abort_unless($request->user()->can('finance.insurance.manage'), 403);
        InsuranceProvider::create($request->validate(['code' => 'required|unique:insurance_providers', 'name' => 'required', 'contact' => 'nullable', 'email' => 'nullable|email']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Provider added.']);
    }

    public function storePlan(Request $request)
    {
        abort_unless($request->user()->can('finance.insurance.manage'), 403);
        InsurancePlan::create($request->validate(['insurance_provider_id' => 'required|exists:insurance_providers,id', 'code' => 'required|unique:insurance_plans', 'name' => 'required', 'coverage_percent' => 'required|numeric|min:0|max:100', 'copay_fixed' => 'nullable|numeric|min:0', 'annual_limit' => 'nullable|numeric|min:0', 'per_visit_limit' => 'nullable|numeric|min:0', 'requires_preauth' => 'boolean']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Plan added.']);
    }

    public function storeMembership(Request $request)
    {
        abort_unless($request->user()->can('finance.insurance.manage'), 403);
        $data = $request->validate(['patient_id' => 'required|exists:patients,id', 'insurance_provider_id' => 'required|exists:insurance_providers,id', 'insurance_plan_id' => 'required|exists:insurance_plans,id', 'member_number' => 'required|string|max:50', 'policy_number' => 'nullable', 'effective_from' => 'nullable|date', 'effective_to' => 'nullable|date']);
        PatientInsuranceMembership::create($data);
        return back()->with('toast', ['type' => 'success', 'message' => 'Membership added.']);
    }

    public function verifyMembership(Request $request, PatientInsuranceMembership $membership, InsuranceService $svc)
    {
        abort_unless($request->user()->can('finance.insurance.verify'), 403);
        $svc->verifyMembership($membership, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Verified.']);
    }

    public function storeClaim(Request $request, InsuranceService $svc, PatientAccessService $access)
    {
        abort_unless($request->user()->can('finance.insurance.claims.manage'), 403);
        $data = $request->validate(['membership_id' => 'required|exists:patient_insurance_memberships,id', 'invoice_id' => 'required|exists:invoices,id']);
        $m = PatientInsuranceMembership::findOrFail($data['membership_id']);
        abort_unless(($p = $m->patient) && $access->canAccess($request->user(), $p), 403);
        $c = $svc->createClaim($m, $data['invoice_id'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Claim {$c->claim_number}"]);
    }

    public function transitionClaim(Request $request, InsuranceClaim $claim, InsuranceService $svc)
    {
        abort_unless($request->user()->can('finance.insurance.claims.manage'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(InsuranceService::CLAIM_TRANSITIONS))], 'reason' => 'nullable', 'amount' => 'nullable|numeric|min:0', 'method' => 'nullable|string|max:50']);
        $svc->transitionClaim($claim, $data['status'], $request->user()->id, $data);
        return back()->with('toast', ['type' => 'success', 'message' => "Claim {$data['status']}"]);
    }

    public function storePreauth(Request $request, InsuranceService $svc, PatientAccessService $access)
    {
        $data = $request->validate(['patient_insurance_membership_id' => 'required|exists:patient_insurance_memberships,id', 'clinical_service_id' => 'nullable|exists:clinical_services,id', 'estimated_cost' => 'nullable|numeric|min:0', 'justification' => 'nullable']);
        $m = PatientInsuranceMembership::findOrFail($data['patient_insurance_membership_id']);
        abort_unless(($p = $m->patient) && $access->canAccess($request->user(), $p), 403);
        $pa = $svc->createPreauth($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Preauth {$pa->auth_number}"]);
    }

    public function transitionPreauth(Request $request, InsurancePreauthorization $preauth, InsuranceService $svc)
    {
        abort_unless($request->user()->can('finance.insurance.claims.manage'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(InsuranceService::PA_TRANSITIONS))], 'amount' => 'nullable|numeric|min:0', 'sessions' => 'nullable|integer', 'provider_ref' => 'nullable|string|max:100']);
        $svc->transitionPreauth($preauth, $data['status'], $request->user()->id, $data);
        return back()->with('toast', ['type' => 'success', 'message' => 'Updated.']);
    }

    public function corporateIndex(Request $request)
    {
        return Inertia::render('admin/finance/corporate', [
            'clients' => CorporateClient::withCount(['sponsorships' => fn ($q) => $q->where('status', 'approved')])->get(),
            'sponsorships' => CorporateSponsorship::with('client:id,name', 'patient:id,mrn,first_name,last_name')->latest()->get(),
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->limit(50)->get(),
        ]);
    }

    public function storeCorporateClient(Request $request)
    {
        abort_unless($request->user()->can('finance.corporate.manage'), 403);
        CorporateClient::create($request->validate(['code' => 'required|unique:corporate_clients', 'name' => 'required', 'contact' => 'nullable', 'email' => 'nullable|email', 'credit_limit' => 'nullable|numeric|min:0', 'payment_terms_days' => 'nullable|integer|min:0']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Client added.']);
    }

    public function storeSponsorship(Request $request)
    {
        abort_unless($request->user()->can('finance.corporate.manage'), 403);
        $data = $request->validate(['corporate_client_id' => 'required|exists:corporate_clients,id', 'patient_id' => 'required|exists:patients,id', 'employee_number' => 'nullable', 'coverage_percent' => 'required|numeric|min:0|max:100', 'benefit_limit' => 'nullable|numeric|min:0', 'effective_from' => 'nullable|date', 'effective_to' => 'nullable|date']);
        CorporateSponsorship::create($data);
        return back()->with('toast', ['type' => 'success', 'message' => 'Sponsorship added.']);
    }
}
