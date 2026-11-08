<?php

namespace Modules\Administration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\JobPosition;
use Modules\Administration\Services\HrService;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Department;

class HrController extends Controller
{
    public function index(Request $request, HrService $svc)
    {
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        return Inertia::render('admin/hr/index', [
            'dashboard' => $svc->dashboard($branchId),
            'employees' => Employee::where('primary_branch_id', $branchId)->with('position:id,title', 'department:id,name')->latest()->paginate(20),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'departments' => Department::get(['id', 'name']),
            'positions' => JobPosition::where('active', true)->get(['id', 'title', 'code']),
        ]);
    }

    public function store(Request $request, HrService $svc)
    {
        abort_unless($request->user()->can('hr.employees.manage'), 403);
        $data = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'middle_name' => 'nullable|string', 'gender' => 'nullable|string', 'date_of_birth' => 'nullable|date', 'category' => 'required|string', 'job_position_id' => 'nullable|exists:job_positions,id', 'job_title' => 'nullable|string', 'department_id' => 'nullable|exists:departments,id', 'primary_branch_id' => 'required|exists:branches,id', 'supervisor_id' => 'nullable|exists:employees,id', 'employment_type' => 'required|string', 'start_date' => 'nullable|date', 'user_id' => 'nullable|exists:users,id']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['primary_branch_id']), 403);
        $e = $svc->register($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Employee {$e->employee_number}"]);
    }

    public function transition(Request $request, Employee $employee, HrService $svc)
    {
        abort_unless($request->user()->can('hr.employees.manage'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(HrService::TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $svc->transition($employee, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Status {$data['status']}"]);
    }

    public function transfer(Request $request, Employee $employee, HrService $svc)
    {
        abort_unless($request->user()->can('hr.employees.transfer'), 403);
        $data = $request->validate(['branch_id' => 'required|exists:branches,id', 'department_id' => 'nullable|exists:departments,id', 'reason' => 'nullable|string|max:500']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $svc->transfer($employee, $data['branch_id'], $data['department_id'] ?? null, $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => 'Transferred.']);
    }

    public function storeContract(Request $request, Employee $employee, HrService $svc)
    {
        abort_unless($request->user()->can('hr.contracts.manage'), 403);
        $data = $request->validate(['type' => 'required|string', 'job_title' => 'nullable|string', 'start_date' => 'required|date', 'end_date' => 'nullable|date|after_or_equal:start_date']);
        $c = $svc->createContract($employee, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Contract {$c->contract_number}"]);
    }

    public function storeLeave(Request $request, \Modules\Administration\Services\LeaveService $svc)
    {
        $data = $request->validate(['employee_id' => 'required|exists:employees,id', 'leave_type_id' => 'required|exists:leave_types,id', 'branch_id' => 'required|exists:branches,id', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'reason' => 'nullable|string|max:500']);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $r = $svc->request($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Leave {$r->request_number}"]);
    }

    public function transitionLeave(Request $request, \Modules\Administration\Models\LeaveRequest $leaveRequest, \Modules\Administration\Services\LeaveService $svc)
    {
        abort_unless($request->user()->can('hr.leave.approve'), 403);
        $data = $request->validate(['status' => 'required|string', 'reason' => 'nullable|string|max:500']);
        $svc->transition($leaveRequest, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Leave {$data['status']}"]);
    }

    public function storePosition(Request $request)
    {
        abort_unless($request->user()->can('hr.positions.manage'), 403);
        JobPosition::create($request->validate(['code' => 'required|unique:job_positions', 'title' => 'required', 'department_id' => 'nullable|exists:departments,id', 'grade' => 'nullable|string']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Position added.']);
    }
}
