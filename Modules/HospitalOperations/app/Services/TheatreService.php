<?php

namespace Modules\HospitalOperations\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\Employee;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\OperatingTheatre;
use Modules\HospitalOperations\Models\SurgicalBooking;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalResourceReservation;
use Modules\HospitalOperations\Models\SurgicalTeamAssignment;
use Modules\Inventory\Models\Equipment;

class TheatreService
{
    public const THEATRE_TRANSITIONS = [
        'available' => ['reserved', 'occupied', 'cleaning', 'maintenance', 'inspection_required', 'out_of_service', 'inactive'],
        'reserved' => ['available', 'occupied', 'maintenance'],
        'occupied' => ['cleaning', 'available'],
        'cleaning' => ['available', 'inspection_required'],
        'maintenance' => ['available', 'out_of_service'],
        'inspection_required' => ['available', 'out_of_service', 'maintenance'],
        'out_of_service' => ['maintenance', 'available', 'inactive'],
        'inactive' => ['available'],
    ];
    public const UNBOOKABLE_STATUSES = ['maintenance', 'inspection_required', 'out_of_service', 'inactive', 'cleaning'];

    private function nextNumber(string $key, string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($key, $prefix, $table, $col) {
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y').'-%')->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'surgery', 'value' => json_encode($seq), 'is_secret' => false, 'created_at' => now(), 'updated_at' => now()]);
            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }

    // ---- Theatre configuration ----
    public function createTheatre(array $data, int $userId): OperatingTheatre
    {
        $t = OperatingTheatre::create($data + ['status' => 'available']);
        $t->statusHistories()->create(['to_status' => 'available', 'changed_by' => $userId]);
        Audit::record('surgery.theatre_created', null, null, ['theatre' => $t->code], $t->branch_id);
        return $t;
    }

    public function setTheatreStatus(OperatingTheatre $t, string $to, int $userId, ?string $reason = null): OperatingTheatre
    {
        $from = $t->status;
        abort_unless(in_array($to, self::THEATRE_TRANSITIONS[$from] ?? []), 422, "Cannot move theatre {$from}→{$to}.");
        DB::transaction(function () use ($t, $from, $to, $userId, $reason) {
            $t->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
            $t->update(['status' => $to]);
        });
        Audit::record('surgery.theatre_status', null, ['status' => $from], ['status' => $to], $t->branch_id);
        return $t->fresh();
    }

    public function setHours(OperatingTheatre $t, int $dow, string $start, string $end, int $userId): void
    {
        abort_if($dow < 0 || $dow > 6 || $start >= $end, 422, 'Invalid hours.');
        $t->hours()->updateOrCreate(['day_of_week' => $dow], ['start_time' => $start, 'end_time' => $end, 'active' => true]);
    }

    public function addException(OperatingTheatre $t, string $date, string $reason, bool $available, int $userId): void
    {
        $t->exceptions()->updateOrCreate(['date' => $date], ['reason' => $reason, 'available' => $available, 'created_by' => $userId]);
        Audit::record('surgery.theatre_exception', null, null, ['theatre' => $t->code, 'date' => $date, 'available' => $available], $t->branch_id);
    }

    // ---- Availability & conflict detection ----
    /** Blocked window for a booking: prep before start, cleaning after end. */
    public function blockedWindow(string $date, string $start, string $end, int $prep = 0, int $clean = 0): array
    {
        $s = Carbon::parse("{$date} {$start}")->subMinutes($prep);
        $e = Carbon::parse("{$date} {$end}")->addMinutes($clean);
        return [$s, $e];
    }

    public function assertTheatreBookable(OperatingTheatre $t, string $date, string $start, string $end, bool $emergency = false): void
    {
        abort_if(in_array($t->status, self::UNBOOKABLE_STATUSES), 422, "Theatre {$t->code} is {$t->status}.");
        $exc = $t->exceptions()->where('date', $date)->first();
        abort_if($exc && ! $exc->available, 422, 'Theatre unavailable on '.$date.': '.$exc?->reason);
        if (! $emergency) {
            $hours = $t->hours()->where('day_of_week', Carbon::parse($date)->dayOfWeek)->where('active', true)->first();
            if ($hours) {
                abort_if($start < $hours->start_time || $end > $hours->end_time, 422, 'Outside theatre operating hours.');
            }
        }
    }

    /** Overlap check on active bookings for the same theatre. */
    public function theatreConflict(OperatingTheatre $t, string $date, string $start, string $end, int $prep, int $clean, ?int $excludeBookingId = null): ?SurgicalBooking
    {
        [$bs, $be] = $this->blockedWindow($date, $start, $end, $prep, $clean);
        return SurgicalBooking::where('operating_theatre_id', $t->id)->where('scheduled_date', $date)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->get()
            ->first(function ($b) use ($bs, $be) {
                [$os, $oe] = $this->blockedWindow($b->scheduled_date->toDateString(), $b->start_time, $b->end_time, $b->prep_minutes, $b->cleaning_minutes);
                return $bs < $oe && $be > $os;
            });
    }

    /** Staff conflict: user already committed on an overlapping active booking. */
    public function staffConflict(array $userIds, Carbon $bs, Carbon $be, ?int $excludeBookingId = null): ?int
    {
        $ids = array_filter($userIds);
        if (! $ids) {
            return null;
        }
        $bookings = SurgicalBooking::whereIn('status', ['scheduled', 'in_progress'])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function ($q) use ($ids) {
                $q->whereIn('surgeon_id', $ids)->orWhereIn('assistant_surgeon_id', $ids)->orWhereIn('anesthetist_id', $ids);
            })->get();
        foreach ($bookings as $b) {
            [$os, $oe] = $this->blockedWindow($b->scheduled_date->toDateString(), $b->start_time, $b->end_time, $b->prep_minutes, $b->cleaning_minutes);
            if ($bs < $oe && $be > $os) {
                foreach (['surgeon_id', 'assistant_surgeon_id', 'anesthetist_id'] as $f) {
                    if (in_array($b->{$f}, $ids)) {
                        return (int) $b->{$f};
                    }
                }
            }
        }
        return null;
    }

    /** Equipment conflict via active reservations. */
    public function equipmentConflict(array $equipmentIds, Carbon $bs, Carbon $be, ?int $excludeBookingId = null): ?int
    {
        $res = SurgicalResourceReservation::where('reservable_type', 'equipment')->where('status', 'active')
            ->whereIn('reservable_id', $equipmentIds)
            ->when($excludeBookingId, fn ($q) => $q->where('surgical_booking_id', '!=', $excludeBookingId))
            ->where('starts_at', '<', $be)->where('ends_at', '>', $bs)->first();
        return $res ? (int) $res->reservable_id : null;
    }

    /** Equipment operational availability per canonical equipment register. */
    public function equipmentUnavailable(array $equipmentIds): array
    {
        if (! $equipmentIds) {
            return [];
        }
        return Equipment::whereIn('id', $equipmentIds)
            ->whereIn('status', ['maintenance', 'out_of_service', 'quarantined', 'retired'])
            ->pluck('id')->all();
    }

    // ---- Booking ----
    public function createBooking(SurgicalCase $c, array $data, int $userId): SurgicalBooking
    {
        $emergency = ($data['emergency_override'] ?? false) && $c->priority === 'emergency';
        $allowedStatuses = $emergency
            ? ['submitted', 'pending_clinical_review', 'pending_surgical_approval', 'approved', 'awaiting_preoperative_assessment', 'awaiting_consent', 'ready_for_scheduling']
            : ['ready_for_scheduling'];
        abort_unless(in_array($c->status, $allowedStatuses), 422, "Case status {$c->status} cannot be scheduled.");

        $theatre = OperatingTheatre::findOrFail($data['operating_theatre_id']);
        abort_unless($theatre->branch_id === $c->branch_id, 422, 'Theatre is in another branch.');
        $prep = (int) ($data['prep_minutes'] ?? $theatre->prep_minutes);
        $clean = (int) ($data['cleaning_minutes'] ?? $theatre->cleaning_minutes);
        abort_if($data['start_time'] >= $data['end_time'], 422, 'Invalid time range.');
        [$bs, $be] = $this->blockedWindow($data['scheduled_date'], $data['start_time'], $data['end_time'], $prep, $clean);

        $this->assertTheatreBookable($theatre, $data['scheduled_date'], $data['start_time'], $data['end_time'], $emergency);
        abort_if($conflict = $this->theatreConflict($theatre, $data['scheduled_date'], $data['start_time'], $data['end_time'], $prep, $clean), 422, "Theatre conflict with booking {$conflict->booking_number}.");
        $staffIds = array_filter([$data['surgeon_id'] ?? $c->surgeon_id, $data['assistant_surgeon_id'] ?? null, $data['anesthetist_id'] ?? null]);
        abort_if($uid = $this->staffConflict($staffIds, $bs, $be), 422, "Staff member #{$uid} has a conflicting booking.");
        $equipIds = $data['equipment_ids'] ?? [];
        abort_if($bad = $this->equipmentUnavailable($equipIds), 422, 'Equipment not operational: '.implode(',', $bad));
        abort_if($eid = $this->equipmentConflict($equipIds, $bs, $be), 422, "Equipment #{$eid} already reserved.");

        return DB::transaction(function () use ($c, $data, $theatre, $prep, $clean, $bs, $be, $equipIds, $emergency, $userId) {
            $b = SurgicalBooking::create([
                'booking_number' => $this->nextNumber('surgery.booking_sequence', 'THB', 'surgical_bookings', 'booking_number'),
                'surgical_case_id' => $c->id, 'patient_id' => $c->patient_id, 'branch_id' => $c->branch_id,
                'operating_theatre_id' => $theatre->id, 'scheduled_date' => $data['scheduled_date'],
                'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
                'prep_minutes' => $prep, 'cleaning_minutes' => $clean,
                'surgeon_id' => $data['surgeon_id'] ?? $c->surgeon_id,
                'assistant_surgeon_id' => $data['assistant_surgeon_id'] ?? null,
                'anesthetist_id' => $data['anesthetist_id'] ?? null,
                'priority' => $c->priority, 'emergency_override' => $emergency,
                'status' => 'scheduled', 'notes' => $data['notes'] ?? null, 'created_by' => $userId,
            ]);
            $b->histories()->create(['action' => 'created', 'after' => $b->toArray(), 'changed_by' => $userId]);
            // Resource reservations (theatre + staff + equipment) — released on cancel
            $b->reservations()->create(['reservable_type' => 'theatre', 'reservable_id' => $theatre->id, 'starts_at' => $bs, 'ends_at' => $be, 'created_by' => $userId]);
            foreach (array_filter([$b->surgeon_id, $b->assistant_surgeon_id, $b->anesthetist_id]) as $uid) {
                $b->reservations()->create(['reservable_type' => 'staff', 'reservable_id' => $uid, 'starts_at' => $bs, 'ends_at' => $be, 'created_by' => $userId]);
            }
            foreach ($equipIds as $eid) {
                $b->reservations()->create(['reservable_type' => 'equipment', 'reservable_id' => $eid, 'starts_at' => $bs, 'ends_at' => $be, 'created_by' => $userId]);
            }
            $from = $c->status;
            $c->statusHistories()->create(['from_status' => $from, 'to_status' => 'scheduled', 'reason' => 'Theatre booking '.$b->booking_number, 'changed_by' => $userId]);
            $c->update(['status' => 'scheduled']);
            Audit::record('surgery.booking_created', $c->patient, null, ['booking' => $b->booking_number, 'theatre' => $theatre->code], $c->branch_id);
            app(SurgicalCaseService::class)->alert('booking.created', $c, "Booking {$b->booking_number} in theatre {$theatre->code} on {$b->scheduled_date->toDateString()} {$b->start_time}.");
            return $b->fresh();
        });
    }

    public function reschedule(SurgicalBooking $b, array $data, int $userId): SurgicalBooking
    {
        abort_unless($b->status === 'scheduled', 422, 'Only scheduled bookings can be rescheduled.');
        $theatre = OperatingTheatre::findOrFail($data['operating_theatre_id'] ?? $b->operating_theatre_id);
        $emergency = $b->emergency_override;
        $prep = (int) ($data['prep_minutes'] ?? $b->prep_minutes);
        $clean = (int) ($data['cleaning_minutes'] ?? $b->cleaning_minutes);
        $date = $data['scheduled_date'] ?? $b->scheduled_date->toDateString();
        $start = $data['start_time'] ?? $b->start_time;
        $end = $data['end_time'] ?? $b->end_time;
        [$bs, $be] = $this->blockedWindow($date, $start, $end, $prep, $clean);

        $this->assertTheatreBookable($theatre, $date, $start, $end, $emergency);
        abort_if($conflict = $this->theatreConflict($theatre, $date, $start, $end, $prep, $clean, $b->id), 422, "Theatre conflict with {$conflict->booking_number}.");
        $staffIds = array_filter([$data['surgeon_id'] ?? $b->surgeon_id, $data['assistant_surgeon_id'] ?? $b->assistant_surgeon_id, $data['anesthetist_id'] ?? $b->anesthetist_id]);
        abort_if($uid = $this->staffConflict($staffIds, $bs, $be, $b->id), 422, "Staff member #{$uid} has a conflicting booking.");

        return DB::transaction(function () use ($b, $data, $theatre, $bs, $be, $date, $start, $end, $prep, $clean, $staffIds, $userId) {
            $before = $b->only(['operating_theatre_id', 'scheduled_date', 'start_time', 'end_time']);
            $b->reservations()->where('status', 'active')->update(['status' => 'released']);
            $b->update([
                'operating_theatre_id' => $theatre->id, 'scheduled_date' => $date, 'start_time' => $start, 'end_time' => $end,
                'prep_minutes' => $prep, 'cleaning_minutes' => $clean,
                'surgeon_id' => $data['surgeon_id'] ?? $b->surgeon_id,
                'assistant_surgeon_id' => $data['assistant_surgeon_id'] ?? $b->assistant_surgeon_id,
                'anesthetist_id' => $data['anesthetist_id'] ?? $b->anesthetist_id,
            ]);
            $b->histories()->create(['action' => 'rescheduled', 'before' => $before, 'after' => $b->fresh()->only(['operating_theatre_id', 'scheduled_date', 'start_time', 'end_time']), 'reason' => $data['reason'] ?? null, 'changed_by' => $userId]);
            $b->reservations()->create(['reservable_type' => 'theatre', 'reservable_id' => $theatre->id, 'starts_at' => $bs, 'ends_at' => $be, 'created_by' => $userId]);
            foreach ($staffIds as $uid) {
                $b->reservations()->create(['reservable_type' => 'staff', 'reservable_id' => $uid, 'starts_at' => $bs, 'ends_at' => $be, 'created_by' => $userId]);
            }
            Audit::record('surgery.booking_rescheduled', $b->patient, $before, $b->only(['scheduled_date', 'start_time', 'end_time']), $b->branch_id);
            return $b->fresh();
        });
    }

    public function cancelBooking(SurgicalBooking $b, string $reason, int $userId): SurgicalBooking
    {
        abort_unless(in_array($b->status, ['scheduled']), 422, 'Only scheduled bookings can be cancelled.');
        return DB::transaction(function () use ($b, $reason, $userId) {
            $b->reservations()->where('status', 'active')->update(['status' => 'released']);
            $b->histories()->create(['action' => 'cancelled', 'before' => $b->only(['scheduled_date', 'start_time', 'end_time']), 'reason' => $reason, 'changed_by' => $userId]);
            $b->update(['status' => 'cancelled']);
            $c = $b->case;
            if ($c->status === 'scheduled') {
                $c->statusHistories()->create(['from_status' => 'scheduled', 'to_status' => 'ready_for_scheduling', 'reason' => "Booking {$b->booking_number} cancelled", 'changed_by' => $userId]);
                $c->update(['status' => 'ready_for_scheduling']);
            }
            Audit::record('surgery.booking_cancelled', $b->patient, null, ['booking' => $b->booking_number], $b->branch_id);
            return $b->fresh();
        });
    }

    // ---- Team assignment ----
    public function assignStaff(SurgicalCase $c, array $data, int $userId): SurgicalTeamAssignment
    {
        abort_unless(in_array($data['role'], SurgicalTeamAssignment::ROLES), 422, 'Invalid role.');
        $employee = Employee::where('user_id', $data['user_id'])->where('status', 'active')->first();
        // Require linked active employee record for clinical roles when employees module data exists
        if (Employee::where('user_id', $data['user_id'])->exists()) {
            abort_unless($employee, 422, 'Employee record is not active.');
        }
        if ($employee) {
            abort_unless((int) $employee->primary_branch_id === (int) $c->branch_id
                || $employee->assignments()->where('branch_id', $c->branch_id)->where('status', 'active')->exists(),
                422, 'Employee not assigned to this branch.');
        }
        // Prevent double assignment of the same person to an active role on this case
        abort_if($c->teamAssignments()->where('user_id', $data['user_id'])->where('role', $data['role'])->whereIn('status', ['assigned', 'confirmed'])->exists(), 422, 'Already assigned.');
        // Staff conflict against active bookings overlapping this case's booking window
        if ($booking = $c->bookings()->whereIn('status', ['scheduled', 'in_progress'])->latest()->first()) {
            [$bs, $be] = $this->blockedWindow($booking->scheduled_date->toDateString(), $booking->start_time, $booking->end_time, $booking->prep_minutes, $booking->cleaning_minutes);
            abort_if($uid = $this->staffConflict([$data['user_id']], $bs, $be, $booking->id), 422, 'Staff has a conflicting assignment.');
        }
        $a = $c->teamAssignments()->create($data + [
            'branch_id' => $c->branch_id, 'employee_id' => $employee?->id, 'status' => 'assigned', 'assigned_by' => $userId,
        ]);
        $a->histories()->create(['action' => 'assigned', 'changed_by' => $userId]);
        Audit::record('surgery.team_assigned', $c->patient, null, ['user' => $data['user_id'], 'role' => $data['role']], $c->branch_id);
        return $a;
    }

    public function confirmAssignment(SurgicalTeamAssignment $a, int $userId): SurgicalTeamAssignment
    {
        abort_unless($a->status === 'assigned', 422);
        abort_unless($a->user_id === $userId, 403, 'Only the assigned staff member can confirm.');
        $a->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $a->histories()->create(['action' => 'confirmed', 'changed_by' => $userId]);
        return $a->fresh();
    }

    public function replaceAssignment(SurgicalTeamAssignment $a, int $newUserId, string $reason, int $userId): SurgicalTeamAssignment
    {
        abort_unless(in_array($a->status, ['assigned', 'confirmed']), 422);
        return DB::transaction(function () use ($a, $newUserId, $reason, $userId) {
            $a->update(['status' => 'replaced', 'replaced_by_id' => $newUserId]);
            $a->histories()->create(['action' => 'replaced', 'reason' => $reason, 'changed_by' => $userId]);
            $new = $this->assignStaff($a->case, ['user_id' => $newUserId, 'role' => $a->role, 'surgical_booking_id' => $a->surgical_booking_id], $userId);
            Audit::record('surgery.team_replaced', $a->case->patient, null, ['role' => $a->role, 'new_user' => $newUserId], $a->branch_id);
            return $new;
        });
    }

    public function removeAssignment(SurgicalTeamAssignment $a, string $reason, int $userId): void
    {
        abort_unless(in_array($a->status, ['assigned', 'confirmed']), 422);
        $a->update(['status' => 'removed']);
        $a->histories()->create(['action' => 'removed', 'reason' => $reason, 'changed_by' => $userId]);
        Audit::record('surgery.team_removed', $a->case->patient, null, ['role' => $a->role], $a->branch_id);
    }

    // ---- Calendar ----
    public function calendar(int $branchId, string $from, string $to, array $filters = [])
    {
        return SurgicalBooking::where('branch_id', $branchId)->whereBetween('scheduled_date', [$from, $to])
            ->when($filters['theatre_id'] ?? null, fn ($q, $v) => $q->where('operating_theatre_id', $v))
            ->when($filters['surgeon_id'] ?? null, fn ($q, $v) => $q->where('surgeon_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->with(['case:id,case_number,priority,status,surgical_specialty_id', 'case.specialty:id,name', 'patient:id,mrn,first_name,last_name', 'theatre:id,code,name', 'surgeon:id,name', 'anesthetist:id,name'])
            ->orderBy('scheduled_date')->orderBy('start_time')->get();
    }
}
