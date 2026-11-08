<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Appointment;
use Modules\HospitalOperations\Models\PatientVisit;
use Modules\HospitalOperations\Models\QueueEntry;
use Modules\HospitalOperations\Models\QueueType;
use Modules\HospitalOperations\Services\QueueService;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($user)->pluck('id')->first();
        $queues = QueueType::where('active', true)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get();
        $entries = QueueEntry::whereIn('queue_type_id', $queues->pluck('id'))->whereDate('created_at', $request->date ?? today())->whereIn('status', ['waiting','called','in_service','hold'])->with('visit.patient:id,mrn,first_name,last_name')->get();
        return Inertia::render('admin/queue/index', [
            'queues' => $queues->map(fn ($qt) => $qt->only(['id','name','code','department'])),
            'entries' => $entries,
            'branches' => BranchContext::allowed($user)->values(['id','name']),
            'priorities' => array_keys(QueueService::PRIORITIES),
            'analytics' => $queues->mapWithKeys(fn ($qt) => [$qt->id => app(QueueService::class)->analytics($qt->id)]),
        ]);
    }

    public function createVisit(Request $request)
    {
        return Inertia::render('admin/queue/form', [
            'patients' => Patient::select('id','mrn','first_name','last_name')->limit(50)->get(),
            'branches' => BranchContext::allowed($request->user())->values(['id','name']),
            'queueTypes' => QueueType::where('active', true)->get(['id','name','branch_id']),
            'priorities' => array_keys(QueueService::PRIORITIES),
            'appointments' => Appointment::where('appointment_date', today())->whereIn('status', ['confirmed','pending_confirmation'])->with('patient:id,mrn,first_name,last_name')->get(['id','appointment_number','patient_id','branch_id','clinician_id']),
        ]);
    }

    public function storeVisit(Request $request, QueueService $svc, PatientAccessService $access)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id', 'branch_id' => 'required|exists:branches,id',
            'queue_type_id' => 'required|exists:queue_types,id', 'source' => ['required', Rule::in(QueueService::SOURCES)],
            'appointment_id' => 'nullable|exists:appointments,id', 'priority' => ['nullable', Rule::in(array_keys(QueueService::PRIORITIES))],
            'reason' => 'nullable|string|max:500',
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless($access->canAccess($request->user(), $patient), 403);
        abort_unless(BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        if ($data['appointment_id']) {
            $appt = Appointment::findOrFail($data['appointment_id']);
            abort_unless($appt->patient_id === $patient->id && $appt->branch_id === (int) $data['branch_id'], 422);
            // prevent duplicate active queue entries for the same appointment
            abort_if(PatientVisit::where('appointment_id', $appt->id)->whereNotIn('status', ['cancelled','completed','no_show'])->exists(), 422, 'Visit already exists.');
        }
        $visit = $svc->createVisit($data, $request->user()->id);
        return redirect()->route('admin.queue.show', $visit)->with('toast', ['type' => 'success', 'message' => "Visit {$visit->visit_number}"]);
    }

    public function show(Request $request, PatientVisit $visit, PatientAccessService $access)
    {
        abort_unless(($p = $visit->patient) && $access->canAccess($request->user(), $p), 403);
        return Inertia::render('admin/queue/show', ['visit' => $visit->load(['patient','branch','appointment','queueEntries.statusHistories.changedBy','queueEntries.transfers','queueEntries.clinician:id,name','queueEntries.queueType:id,name'])]);
    }

    public function transition(Request $request, PatientVisit $visit, QueueService $svc, PatientAccessService $access)
    {
        abort_unless(($p = $visit->patient) && $access->canAccess($request->user(), $p), 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(QueueService::VISIT_TRANSITIONS))], 'reason' => 'nullable|string|max:500']);
        $perm = match ($data['status']) { 'checked_in' => 'queue.check_in', 'completed' => 'queue.complete_visit', 'called', 'in_service' => 'queue.call', default => 'queue.manage' };
        abort_unless($request->user()->can($perm), 403);
        $svc->transitionVisit($visit, $data['status'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => "Visit {$data['status']}"]);
    }

    public function callNext(Request $request, QueueService $svc)
    {
        $data = $request->validate(['queue_type_id' => 'required|exists:queue_types,id']);
        abort_unless($request->user()->can('queue.call'), 403);
        $e = $svc->callNext($data['queue_type_id'], $request->user()->id);
        abort_unless($e, 422, 'Queue empty.');
        return back()->with('toast', ['type' => 'success', 'message' => "Called {$e->ticket_number}"]);
    }

    public function callEntry(Request $request, QueueEntry $entry, QueueService $svc)
    {
        abort_unless($request->user()->can('queue.call'), 403);
        abort_unless($svc->call($entry, $request->user()->id), 422, 'Entry already claimed.');
        return back()->with('toast', ['type' => 'success', 'message' => "Called {$entry->ticket_number}"]);
    }

    public function entryAction(Request $request, QueueEntry $entry, QueueService $svc)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['skip','requeue','hold','remove'])], 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can('queue.manage'), 403);
        match ($data['action']) {
            'skip' => $svc->skip($entry, $request->user()->id, $data['reason'] ?? 'Skipped'),
            'requeue' => $svc->requeue($entry, $request->user()->id),
            'hold' => $svc->setEntryStatus ?? null,
            'remove' => $svc->requeue($entry, $request->user()->id),
        };
        return back()->with('toast', ['type' => 'success', 'message' => 'Done.']);
    }

    public function transfer(Request $request, QueueEntry $entry, QueueService $svc)
    {
        $data = $request->validate(['to_queue_type_id' => 'required|exists:queue_types,id', 'reason' => 'required|string|max:500']);
        abort_unless($request->user()->can('queue.transfer'), 403);
        $svc->transfer($entry, $data['to_queue_type_id'], $request->user()->id, $data['reason']);
        return back()->with('toast', ['type' => 'success', 'message' => 'Transferred.']);
    }

    public function setPriority(Request $request, QueueEntry $entry, QueueService $svc)
    {
        $data = $request->validate(['priority' => ['required', Rule::in(array_keys(QueueService::PRIORITIES))], 'reason' => 'nullable|string|max:500']);
        abort_unless($request->user()->can('queue.change_priority'), 403);
        $svc->setPriority($entry, $data['priority'], $request->user()->id, $data['reason'] ?? null);
        return back()->with('toast', ['type' => 'success', 'message' => 'Priority set.']);
    }

    public function analytics(Request $request, QueueService $svc)
    {
        abort_unless($request->user()->can('queue.view_analytics'), 403);
        $data = $request->validate(['queue_type_id' => 'required|exists:queue_types,id', 'date' => 'nullable|date']);
        return response()->json($svc->analytics($data['queue_type_id'], $data['date'] ?? null));
    }

    public function storeQueueType(Request $request)
    {
        abort_unless($request->user()->can('queue.manage_types'), 403);
        QueueType::create($request->validate(['code' => 'required|string|max:20', 'name' => 'required', 'department' => 'nullable', 'branch_id' => 'required|exists:branches,id']));
        return back()->with('toast', ['type' => 'success', 'message' => 'Queue added.']);
    }

    public function printTicket(Request $request, PatientVisit $visit, PatientAccessService $access)
    {
        abort_unless(($p = $visit->patient) && $access->canAccess($request->user(), $p), 403);
        $entry = $visit->activeEntry() ?? $visit->queueEntries()->latest()->first();
        return view('queue::ticket', ['visit' => $visit->load('patient','branch'), 'entry' => $entry?->load('queueType','clinician'), 'hospital' => \Modules\Foundation\Models\HospitalProfile::first()]);
    }

    public function display(Request $request)
    {
        abort_unless($request->user()->can('queue.view'), 403);
        $branchId = $request->integer('branch_id') ?: BranchContext::allowed($request->user())->pluck('id')->first();
        $queues = QueueType::where('active', true)->where('branch_id', $branchId)->get();
        $entries = QueueEntry::whereIn('queue_type_id', $queues->pluck('id'))->whereIn('status', ['waiting','called'])->with('queueType:id,name')->get();
        return Inertia::render('admin/queue/display', ['entries' => $entries, 'branch' => \Modules\Foundation\Models\Branch::find($branchId)]);
    }
}
