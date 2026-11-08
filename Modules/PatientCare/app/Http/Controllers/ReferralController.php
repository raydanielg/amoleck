<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Models\PatientReferral;
use Modules\PatientCare\Services\PatientAccessService;
use Modules\PatientCare\Services\ReferralService;

class ReferralController extends Controller
{
    public function index(Request $request, PatientAccessService $access)
    {
        $refs = PatientReferral::with('patient:id,mrn,first_name,last_name', 'receivingBranch:id,name')
            ->when(! $request->user()->can('patientcare.patients.view_all_branches') && ! $request->user()->hasRole('Super Admin'),
                fn ($q) => $q->whereIn('receiving_branch_id', BranchContext::allowed($request->user())->pluck('id')->all() ?: [-1]))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('admin/referrals/index', ['referrals' => $refs, 'filters' => $request->only('status'), 'statuses' => ReferralService::STATUSES]);
    }

    public function create(Request $request, Patient $patient)
    {
        return Inertia::render('admin/referrals/form', [
            'patient' => $patient->only(['id', 'mrn', 'first_name', 'last_name']),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
        ]);
    }

    public function store(Request $request, Patient $patient, ReferralService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $patient), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['internal', 'external'])],
            'referral_date' => 'required|date',
            'receiving_branch_id' => 'nullable|exists:branches,id',
            'referrer_name' => 'nullable|string|max:255',
            'referrer_facility' => 'nullable|string|max:255',
            'referrer_designation' => 'nullable|string|max:255',
            'referrer_contact' => 'nullable|string|max:255',
            'referrer_address' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
            'presenting_complaint' => 'nullable|string|max:1000',
            'relevant_history' => 'nullable|string',
            'preliminary_diagnosis' => 'nullable|string|max:1000',
            'requested_service' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        $ref = $svc->create($data + ['patient_id' => $patient->id], $request->user()->id);

        return redirect()->route('admin.patients.show', $patient)->with('toast', ['type' => 'success', 'message' => "Referral {$ref->ref_number} created."]);
    }

    public function transition(Request $request, PatientReferral $referral, ReferralService $svc, PatientAccessService $access)
    {
        abort_unless($access->canAccess($request->user(), $referral->patient), 403);
        $data = $request->validate(['status' => ['required', Rule::in(ReferralService::STATUSES)], 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can(ReferralService::PERMS[$data['status']]), 403);
        $svc->transition($referral, $data['status'], $data['reason'] ?? null, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Referral updated.']);
    }
}
