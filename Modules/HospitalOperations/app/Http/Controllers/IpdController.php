<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\Bed;
use Modules\HospitalOperations\Models\Room;
use Modules\HospitalOperations\Models\Ward;
use Modules\HospitalOperations\Services\IpdService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class IpdController extends Controller
{
    public function index(Request $request, IpdService $svc)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($user)->pluck('id')->first();
        return Inertia::render('admin/ipd/index', [
            'census' => $svc->census($branchId),
            'admissions' => Admission::where('branch_id', $branchId)->with('patient:id,mrn,first_name,last_name', 'bed:id,code', 'ward:id,name')->latest()->paginate(15),
            'beds' => Bed::where('branch_id', $branchId)->with('room:id,name', 'ward:id,name')->get(),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'wards' => Ward::where('branch_id', $branchId)->where('active', true)->get(['id', 'name', 'code']),
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->limit(50)->get(),
            'types' => ['emergency', 'elective', 'planned', 'direct', 'transfer_in', 'other'],
            'sources' => ['opd', 'emergency', 'referral', 'specialist', 'direct', 'transfer_facility'],
            'dischargeTypes' => ['routine', 'against_advice', 'transfer_facility', 'referral', 'deceased', 'other'],
        ]);
    }

    public function storeAdmission(Request $request, IpdService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id', 'clinician_id' => 'nullable|exists:users,id',
            'type' => 'required|string', 'source' => 'required|string', 'priority' => 'nullable|string',
            'reason' => 'nullable|string|max:1000',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $a = $svc->createAdmission($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Admission {$a->admission_number}"]);
    }

    public function transition(Request $request, Admission $admission, IpdService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $admission->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(IpdService::ADM_TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'approved' => 'ipd.admissions.approve', 'discharged' => 'ipd.discharge', 'discharge_pending' => 'ipd.discharge', default => 'ipd.admissions.manage' };
        abort_unless($request->user()->can($perm), 403);
        $svc->transition($admission, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Status: {$data['status']}"]);
    }

    public function allocate(Request $request, Admission $admission, IpdService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $admission->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['bed_id' => 'required|exists:beds,id']);
        abort_unless($request->user()->can('ipd.beds.manage'), 403);
        $svc->allocateBed($admission, Bed::findOrFail($data['bed_id']), $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Bed allocated.']);
    }

    public function transfer(Request $request, Admission $admission, IpdService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $admission->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['bed_id' => 'required|exists:beds,id', 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can('ipd.transfer'), 403);
        $svc->transfer($admission, Bed::findOrFail($data['bed_id']), $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => 'Transferred.']);
    }

    public function discharge(Request $request, Admission $admission, IpdService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $admission->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['type' => 'required|string', 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can('ipd.discharge'), 403);
        $svc->discharge($admission, $data['type'], $data['reason'] ?? null, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Discharged.']);
    }

    public function cleanBed(Request $request, Bed $bed, IpdService $svc)
    {
        abort_unless($request->user()->can('ipd.beds.manage'), 403);
        $svc->completeCleaning($bed, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Bed available.']);
    }

    public function storeWard(Request $request)
    {
        abort_unless($request->user()->can('ipd.wards.manage'), 403);
        Ward::create($request->validate(['code' => 'required|unique:wards', 'name' => 'required', 'branch_id' => 'required|exists:branches,id', 'category' => 'nullable', 'department_id' => 'nullable|exists:departments,id', 'capacity' => 'nullable|integer']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Ward created.']);
    }

    public function storeRoom(Request $request)
    {
        abort_unless($request->user()->can('ipd.wards.manage'), 403);
        $data = $request->validate(['code' => 'required|unique:rooms', 'name' => 'required', 'ward_id' => 'required|exists:wards,id', 'category' => 'nullable', 'capacity' => 'nullable|integer']);
        $ward = Ward::findOrFail($data['ward_id']);
        Room::create($data + ['branch_id' => $ward->branch_id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Room created.']);
    }

    public function storeBed(Request $request)
    {
        abort_unless($request->user()->can('ipd.wards.manage'), 403);
        $data = $request->validate(['code' => 'required|unique:beds', 'number' => 'required', 'room_id' => 'required|exists:rooms,id', 'category' => 'nullable']);
        $room = Room::findOrFail($data['room_id']);
        Bed::create($data + ['ward_id' => $room->ward_id, 'branch_id' => $room->branch_id]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Bed created.']);
    }
}
