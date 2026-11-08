<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Department;
use Modules\HospitalOperations\Models\OperatingTheatre;
use Modules\HospitalOperations\Models\SurgicalBooking;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalTeamAssignment;
use Modules\HospitalOperations\Services\SurgicalIntegrationService;
use Modules\HospitalOperations\Services\TheatreService;
use Modules\Inventory\Models\Equipment;
use Modules\PatientCare\Services\PatientAccessService;

class TheatreController extends Controller
{
    private function guardCase(Request $request, SurgicalCase $case): void
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $case->branch_id), 403);
        abort_unless(app(PatientAccessService::class)->canAccess($request->user(), $case->patient), 403);
    }

    public function theatres(Request $request)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        return Inertia::render('admin/surgery/theatres', [
            'theatres' => OperatingTheatre::where('branch_id', $branchId)->with(['hours', 'exceptions' => fn ($q) => $q->where('date', '>=', today())])->get(),
            'departments' => Department::where('status', 'active')->get(['id', 'name']),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
            'statuses' => OperatingTheatre::STATUSES,
            'categories' => ['general', 'minor', 'emergency', 'orthopedic', 'obstetric', 'day_surgery', 'other'],
        ]);
    }

    public function storeTheatre(Request $request, TheatreService $svc)
    {
        abort_unless($request->user()->can('surgery.theatres.manage'), 403);
        $data = $request->validate([
            'code' => 'required|string|unique:operating_theatres,code', 'name' => 'required|string',
            'branch_id' => 'required|exists:branches,id', 'department_id' => 'nullable|exists:departments,id',
            'category' => 'nullable|string', 'location' => 'nullable|string',
            'prep_minutes' => 'nullable|integer|min:0', 'cleaning_minutes' => 'nullable|integer|min:0',
        ]);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $t = $svc->createTheatre($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Theatre {$t->name} created."]);
    }

    public function theatreStatus(Request $request, OperatingTheatre $theatre, TheatreService $svc)
    {
        abort_unless($request->user()->can('surgery.theatres.manage'), 403);
        $data = $request->validate(['status' => 'required|string', 'reason' => 'nullable|string|max:500']);
        $svc->setTheatreStatus($theatre, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Theatre {$data['status']}."]);
    }

    public function setHours(Request $request, OperatingTheatre $theatre, TheatreService $svc)
    {
        abort_unless($request->user()->can('surgery.theatres.manage'), 403);
        $data = $request->validate(['day_of_week' => 'required|integer|min:0|max:6', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i']);
        $svc->setHours($theatre, (int) $data['day_of_week'], $data['start_time'], $data['end_time'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Hours saved.']);
    }

    public function addException(Request $request, OperatingTheatre $theatre, TheatreService $svc)
    {
        abort_unless($request->user()->can('surgery.theatres.manage'), 403);
        $data = $request->validate(['date' => 'required|date', 'reason' => 'required|string|max:300', 'available' => 'nullable|boolean']);
        $svc->addException($theatre, $data['date'], $data['reason'], (bool) ($data['available'] ?? false), $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Exception recorded.']);
    }

    public function calendar(Request $request, TheatreService $svc, SurgicalIntegrationService $int)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        $from = $request->get('from', now()->startOfWeek()->toDateString());
        $to = $request->get('to', now()->endOfWeek()->toDateString());
        return Inertia::render('admin/surgery/calendar', [
            'bookings' => $svc->calendar($branchId, $from, $to, $request->only(['theatre_id', 'surgeon_id', 'status'])),
            'theatres' => OperatingTheatre::where('branch_id', $branchId)->get(['id', 'code', 'name', 'status', 'category']),
            'readyCases' => SurgicalCase::where('branch_id', $branchId)->whereIn('status', ['ready_for_scheduling'])
                ->with(['patient:id,mrn,first_name,last_name', 'procedure:id,name,expected_duration_minutes', 'specialty:id,name'])->get(['id', 'case_number', 'patient_id', 'surgical_procedure_id', 'surgical_specialty_id', 'priority', 'surgeon_id', 'proposed_date', 'branch_id']),
            'emergencyCases' => SurgicalCase::where('branch_id', $branchId)->where('priority', 'emergency')
                ->whereIn('status', ['submitted', 'pending_clinical_review', 'pending_surgical_approval', 'approved', 'awaiting_preoperative_assessment', 'awaiting_consent'])
                ->with('patient:id,mrn,first_name,last_name')->get(['id', 'case_number', 'patient_id', 'priority', 'status', 'surgeon_id', 'branch_id']),
            'staff' => $int->eligibleStaff($branchId),
            'equipment' => Equipment::where('branch_id', $branchId)->where('status', 'active')->get(['id', 'equipment_number', 'name']),
            'filters' => $request->only(['from', 'to', 'theatre_id', 'surgeon_id', 'status']) + compact('from', 'to', 'branchId'),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
        ]);
    }

    public function book(Request $request, SurgicalCase $case, TheatreService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.scheduling.create'), 403);
        $data = $request->validate([
            'operating_theatre_id' => 'required|exists:operating_theatres,id',
            'scheduled_date' => 'required|date', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i',
            'surgeon_id' => 'nullable|exists:users,id', 'assistant_surgeon_id' => 'nullable|exists:users,id',
            'anesthetist_id' => 'nullable|exists:users,id', 'equipment_ids' => 'nullable|array', 'equipment_ids.*' => 'exists:equipment,id',
            'prep_minutes' => 'nullable|integer|min:0', 'cleaning_minutes' => 'nullable|integer|min:0',
            'emergency_override' => 'nullable|boolean', 'notes' => 'nullable|string|max:1000',
        ]);
        $b = $svc->createBooking($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Booking {$b->booking_number}."]);
    }

    public function reschedule(Request $request, SurgicalBooking $booking, TheatreService $svc)
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $booking->branch_id), 403);
        abort_unless($request->user()->can('surgery.scheduling.update'), 403);
        $data = $request->validate([
            'operating_theatre_id' => 'nullable|exists:operating_theatres,id',
            'scheduled_date' => 'nullable|date', 'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i',
            'surgeon_id' => 'nullable|exists:users,id', 'assistant_surgeon_id' => 'nullable|exists:users,id',
            'anesthetist_id' => 'nullable|exists:users,id', 'reason' => 'required|string|max:500',
        ]);
        $svc->reschedule($booking, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Booking rescheduled.']);
    }

    public function cancelBooking(Request $request, SurgicalBooking $booking, TheatreService $svc)
    {
        abort_unless(BranchContext::canAccess($request->user(), (int) $booking->branch_id), 403);
        abort_unless($request->user()->can('surgery.scheduling.cancel'), 403);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $svc->cancelBooking($booking, $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Booking cancelled; reservations released.']);
    }

    public function assignStaff(Request $request, SurgicalCase $case, TheatreService $svc)
    {
        $this->guardCase($request, $case);
        abort_unless($request->user()->can('surgery.team.manage'), 403);
        $data = $request->validate(['user_id' => 'required|exists:users,id', 'role' => 'required|string', 'surgical_booking_id' => 'nullable|exists:surgical_bookings,id']);
        $svc->assignStaff($case, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Team member assigned.']);
    }

    public function confirmAssignment(Request $request, SurgicalTeamAssignment $assignment, TheatreService $svc)
    {
        $svc->confirmAssignment($assignment, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Assignment confirmed.']);
    }

    public function replaceAssignment(Request $request, SurgicalTeamAssignment $assignment, TheatreService $svc)
    {
        $this->guardCase($request, $assignment->case);
        abort_unless($request->user()->can('surgery.team.manage'), 403);
        $data = $request->validate(['new_user_id' => 'required|exists:users,id', 'reason' => 'required|string|max:500']);
        $svc->replaceAssignment($assignment, (int) $data['new_user_id'], $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Team member replaced.']);
    }

    public function removeAssignment(Request $request, SurgicalTeamAssignment $assignment, TheatreService $svc)
    {
        $this->guardCase($request, $assignment->case);
        abort_unless($request->user()->can('surgery.team.manage'), 403);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $svc->removeAssignment($assignment, $data['reason'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Team member removed.']);
    }
}
