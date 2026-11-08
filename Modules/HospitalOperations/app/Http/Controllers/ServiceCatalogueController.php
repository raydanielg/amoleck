<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\HospitalOperations\Models\Department;
use Modules\HospitalOperations\Models\ServiceCategory;
use Modules\HospitalOperations\Services\ServiceCatalogueService;

class ServiceCatalogueController extends Controller
{
    public function departments(Request $request)
    {
        return Inertia::render('admin/departments/index', [
            'departments' => Department::with('parent:id,name', 'head:id,name')->withCount('children')->paginate(20),
            'allDepartments' => Department::where('status', 'active')->get(['id','name','code']),
            'branches' => BranchContext::allowed($request->user())->values(['id','name']),
            'staff' => \App\Models\User::role(['Doctor','Physiotherapist','Medical Director','Branch Administrator'])->get(['id','name']),
        ]);
    }

    public function storeDepartment(Request $request, ServiceCatalogueService $svc)
    {
        $data = $request->validate(['code' => 'required|string|max:20|unique:departments', 'name' => 'required', 'category' => 'nullable', 'description' => 'nullable', 'head_id' => 'nullable|exists:users,id', 'parent_id' => 'nullable|exists:departments,id', 'contact' => 'nullable', 'operating_hours' => 'nullable']);
        $svc->createDepartment($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Department created.']);
    }

    public function updateDepartment(Request $request, Department $department, ServiceCatalogueService $svc)
    {
        $data = $request->validate(['name' => 'sometimes', 'category' => 'nullable', 'description' => 'nullable', 'head_id' => 'nullable|exists:users,id', 'parent_id' => 'nullable|exists:departments,id', 'contact' => 'nullable', 'operating_hours' => 'nullable', 'status' => 'nullable|in:active,inactive']);
        $svc->updateDepartment($department, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Department updated.']);
    }

    public function assignBranches(Request $request, Department $department, ServiceCatalogueService $svc)
    {
        $data = $request->validate(['branch_ids' => 'required|array', 'branch_ids.*' => 'exists:branches,id']);
        $svc->syncDepartmentBranches($department, $data['branch_ids']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Branches updated.']);
    }

    public function services(Request $request)
    {
        return Inertia::render('admin/services/index', [
            'services' => ClinicalService::with('category:id,name', 'department:id,name')->latest()->paginate(20),
            'categories' => ServiceCategory::where('active', true)->get(['id','name','code']),
            'departments' => Department::where('status', 'active')->get(['id','name','code']),
            'branches' => BranchContext::allowed($request->user())->values(['id','name']),
            'clinicians' => \App\Models\User::role(['Physiotherapist','Doctor'])->get(['id','name']),
            'workflows' => ServiceCatalogueService::WORKFLOWS,
        ]);
    }

    public function storeService(Request $request, ServiceCatalogueService $svc)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:clinical_services', 'name' => 'required',
            'service_category_id' => 'nullable|exists:service_categories,id', 'department_id' => 'nullable|exists:departments,id',
            'type' => 'nullable|string', 'default_duration' => 'nullable|integer|min:5|max:480',
            'clinician_category' => 'nullable|string', 'workflow_mapping' => ['nullable', Rule::in(ServiceCatalogueService::WORKFLOWS)],
            'requires_appointment' => 'boolean', 'allows_walkin' => 'boolean', 'requires_referral' => 'boolean', 'requires_encounter' => 'boolean',
        ]);
        $svc->createService($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Service created.']);
    }

    public function transitionService(Request $request, ClinicalService $service, ServiceCatalogueService $svc)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active','inactive','archived'])]]);
        $perm = $data['status'] === 'archived' ? 'services.archive' : 'services.activate';
        abort_unless($request->user()->can($perm), 403);
        $svc->transitionService($service, $data['status'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Service {$data['status']}."]);
    }

    public function setAvailability(Request $request, ClinicalService $service, ServiceCatalogueService $svc)
    {
        $data = $request->validate(['branch_id' => 'required|exists:branches,id', 'available' => 'required|boolean']);
        $svc->setBranchAvailability($service, $data['branch_id'], $data['available']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Availability updated.']);
    }

    public function assignClinician(Request $request, ClinicalService $service)
    {
        $data = $request->validate(['user_id' => 'required|exists:users,id', 'branch_id' => 'nullable|exists:branches,id', 'is_primary' => 'boolean']);
        $service->clinicians()->create($data);
        return back()->with('toast', ['type' => 'success', 'message' => 'Clinician assigned.']);
    }

    public function storeCategory(Request $request)
    {
        ServiceCategory::create($request->validate(['code' => 'required|unique:service_categories', 'name' => 'required', 'description' => 'nullable', 'parent_id' => 'nullable|exists:service_categories,id']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Category created.']);
    }
}
