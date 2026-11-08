<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Appointment;
use Modules\HospitalOperations\Models\AppointmentType;
use Modules\HospitalOperations\Models\ClinicianSchedule;
use Modules\HospitalOperations\Models\HospitalResource;
use Modules\HospitalOperations\Services\AppointmentService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = Appointment::with('patient:id,mrn,first_name,last_name', 'clinician:id,name', 'branch:id,name', 'type:id,name');
        if (! $user->hasRole('Super Admin') && ! $user->can('appointments.view_all_branches')) {
            $q->whereIn('branch_id', BranchContext::allowed($user)->pluck('id')->all() ?: [-1]);
        }
        return Inertia::render('admin/appointments/index', [
            'appointments' => $q->when($request->status, fn ($q, $s) => $q->where('status', $s))->when($request->date, fn ($q, $d) => $q->where('appointment_date', $d))->latest('appointment_date')->paginate(15)->withQueryString(),
            'stats' => ['today' => (clone $q)->whereDate('appointment_date', today())->count(), 'confirmed' => (clone $q)->where('status', 'confirmed')->count(), 'checked_in' => (clone $q)->where('status', 'checked_in')->count(), 'no_show' => (clone $q)->where('status', 'no_show')->count()],
            'filters' => $request->only('status', 'date'), 'statuses' => Appointment::STATUSES,
        ]);
    }

    public function create(Request $request, PatientAccessService $access)
    {
        return Inertia::render('admin/appointments/form', [
            'patients' => Patient::select('id', 'mrn', 'first_name', 'last_name')->limit(50)->get(),
            'branches' => BranchContext::allowed($request->user())->values(['id', 'name']),
            'types' => AppointmentType::where('active', true)->get(['id', 'name', 'default_duration']),
            'resources' => HospitalResource::where('active', true)->get(['id', 'name', 'branch_id']),
            'clinicians' => \App\Models\User::role(['Physiotherapist', 'Doctor'])->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, AppointmentService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'appointment_type_id' => 'nullable|exists:appointment_types,id', 'clinician_id' => 'nullable|exists:users,id',
            'hospital_resource_id' => 'nullable|exists:hospital_resources,id',
            'appointment_date' => 'required|date|after_or_equal:today', 'start_time' => 'required|date_format:H:i',
            'duration' => 'nullable|integer|min:5|max:480', 'priority' => 'nullable|string', 'reason' => 'nullable|string|max:500', 'notes' => 'nullable|string',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        if ($data['clinician_id'] && ! $svc->clinicianAvailable($data['clinician_id'], $data['branch_id'], $data['appointment_date'], $data['start_time'], \Carbon\Carbon::parse($data['start_time'])->addMinutes($data['duration'] ?? 30)->format('H:i'))) {
            abort(422, 'Clinician not scheduled at this time.');
        }
        $data['end_time'] = \Carbon\Carbon::parse($data['start_time'])->addMinutes($data['duration'] ?? 30)->format('H:i');
        $a = $svc->create($data, $request->user()->id);
        return redirect()->route('admin.appointments.show', $a)->with('toast', ['type' => 'success', 'message' => "Booked {$a->appointment_number}"]);
    }

    public function show(Request $request, Appointment $appointment, PatientAccessService $access)
    {
        abort_unless(($p = $appointment->patient) && $access->canAccess($request->user(), $p), 403);
        return Inertia::render('admin/appointments/show', ['appointment' => $appointment->load(['patient', 'branch', 'type', 'clinician', 'resource', 'statusHistories.changedBy', 'rescheduleHistories.changedBy'])]);
    }

    public function transition(Request $request, Appointment $appointment, AppointmentService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $appointment->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['status' => ['required', Rule::in(Appointment::STATUSES)], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'checked_in' => 'appointments.check_in', 'no_show' => 'appointments.mark_no_show', 'cancelled' => 'appointments.cancel', 'completed' => 'appointments.complete', 'confirmed' => 'appointments.confirm', default => 'appointments.update' };
        abort_unless($request->user()->can($perm), 403);
        $svc->transition($appointment, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Status: {$data['status']}"]);
    }

    public function reschedule(Request $request, Appointment $appointment, AppointmentService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $appointment->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['appointment_date' => 'required|date', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time', 'reason' => 'required|string|max:500']);
        $svc->reschedule($appointment, $data['appointment_date'], $data['start_time'], $data['end_time'], $request->user()->id, $data['reason']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Rescheduled.']);
    }

    public function slots(Request $request, AppointmentService $svc)
    {
        $data = $request->validate(['clinician_id' => 'required|exists:users,id', 'branch_id' => 'required|exists:branches,id', 'date' => 'required|date']);
        return response()->json(['slots' => $svc->availableSlots($data['clinician_id'], $data['branch_id'], $data['date'])]);
    }

    public function storeType(Request $r) { AppointmentType::create($r->validate(['name' => 'required', 'code' => 'required|unique:appointment_types', 'department' => 'nullable', 'default_duration' => 'nullable|integer'])); return back(); }
    public function storeSchedule(Request $r) { ClinicianSchedule::create($r->validate(['user_id' => 'required|exists:users,id', 'branch_id' => 'required|exists:branches,id', 'day_of_week' => 'required|integer|min:0|max:6', 'start_time' => 'required', 'end_time' => 'required', 'break_start' => 'nullable', 'break_end' => 'nullable'])); return back(); }
    public function storeResource(Request $r) { HospitalResource::create($r->validate(['name' => 'required', 'code' => 'required|unique:hospital_resources', 'branch_id' => 'required|exists:branches,id', 'type' => 'nullable'])); return back(); }
}
