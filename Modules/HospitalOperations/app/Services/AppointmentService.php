<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\Appointment;
use Modules\HospitalOperations\Models\ClinicianSchedule;

class AppointmentService
{
    public const TRANSITIONS = [
        'draft' => ['pending_confirmation', 'confirmed', 'cancelled'],
        'pending_confirmation' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'rescheduled', 'cancelled', 'no_show'],
        'checked_in' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'rescheduled' => [],
        'no_show' => [],
    ];

    public function nextNumber(): string
    {
        return DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'ops.appointment_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = Appointment::where('appointment_number', 'like', 'APT-'.date('Y').'-%')->orderByDesc('appointment_number')->value('appointment_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => 'ops.appointment_sequence'], ['group' => 'ops', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf('APT-%s-%06d', date('Y'), $seq);
        });
    }

    /** Conflict check: clinician + resource overlap, same day, excluding $ignoreId. */
    public function hasConflict(string $date, string $start, string $end, ?int $clinicianId, ?int $resourceId, ?int $ignoreId = null): bool
    {
        $q = fn ($col, $id) => Appointment::whereDate('appointment_date', $date)->whereNotIn('status', ['cancelled', 'no_show'])
            ->where($col, $id)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(fn ($q) => $q->where('start_time', '<', $end)->where('end_time', '>', $start));
        return ($clinicianId && $q('clinician_id', $clinicianId)->exists()) || ($resourceId && $q('hospital_resource_id', $resourceId)->exists());
    }

    /** Clinician works this slot per schedule + branch? */
    public function clinicianAvailable(int $clinicianId, int $branchId, string $date, string $start, string $end): bool
    {
        $dow = \Carbon\Carbon::parse($date)->dayOfWeek;
        return ClinicianSchedule::where('user_id', $clinicianId)->where('branch_id', $branchId)->where('day_of_week', $dow)
            ->where('active', true)->where('start_time', '<=', $start)->where('end_time', '>=', $end)
            ->where(fn ($q) => $q->whereNull('break_start')->orWhere(fn ($q) => $q->where('break_start', '>=', $end)->orWhere('break_end', '<=', $start)))
            ->exists();
    }

    public function create(array $data, int $userId): Appointment
    {
        return DB::transaction(function () use ($data, $userId) {
            if ($this->hasConflict($data['appointment_date'], $data['start_time'], $data['end_time'], $data['clinician_id'] ?? null, $data['hospital_resource_id'] ?? null)) {
                abort(422, 'Scheduling conflict: clinician or resource already booked.');
            }
            $a = Appointment::create($data + ['appointment_number' => $this->nextNumber(), 'status' => 'draft', 'created_by' => $userId]);
            $a->statusHistories()->create(['to_status' => 'draft', 'changed_by' => $userId]);
            Audit::record('appointment.created', $a->patient, null, ['appt' => $a->appointment_number], $a->branch_id);
            return $a;
        });
    }

    public function transition(Appointment $a, string $to, int $userId, ?string $reason = null): Appointment
    {
        $from = $a->status;
        abort_unless(in_array($to, static::TRANSITIONS[$from] ?? []), 422, "Cannot move from {$from} to {$to}.");
        abort_if(in_array($to, ['cancelled', 'no_show']) && ! $reason, 422, 'Reason required.');
        DB::transaction(function () use ($a, $to, $from, $userId, $reason) {
            $a->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
            $a->update(['status' => $to] + ($to === 'checked_in' ? ['checked_in_at' => now()] : []));
        });
        Audit::record("appointment.{$to}", $a->patient, ['status' => $from], ['status' => $to], $a->branch_id);
        return $a->fresh();
    }

    public function reschedule(Appointment $a, string $date, string $start, string $end, int $userId, ?string $reason = null): Appointment
    {
        abort_unless(in_array($a->status, ['draft', 'pending_confirmation', 'confirmed']), 422, 'Cannot reschedule in current status.');
        abort_unless(! $this->hasConflict($date, $start, $end, $a->clinician_id, $a->hospital_resource_id, $a->id), 422, 'New slot conflicts.');
        return DB::transaction(function () use ($a, $date, $start, $end, $userId, $reason) {
            $a->rescheduleHistories()->create(['previous_date' => $a->appointment_date, 'previous_start' => $a->start_time, 'previous_end' => $a->end_time, 'new_date' => $date, 'new_start' => $start, 'new_end' => $end, 'reason' => $reason, 'changed_by' => $userId]);
            $a->update(['appointment_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => 'rescheduled']);
            Audit::record('appointment.rescheduled', $a->patient, null, ['to' => $date], $a->branch_id);
            return $a->fresh();
        });
    }

    /** Available slots for a clinician on a date. */
    public function availableSlots(int $clinicianId, int $branchId, string $date, int $duration = 30): array
    {
        $dow = \Carbon\Carbon::parse($date)->dayOfWeek;
        $slots = [];
        foreach (ClinicianSchedule::where('user_id', $clinicianId)->where('branch_id', $branchId)->where('day_of_week', $dow)->where('active', true)->get() as $sch) {
            $t = \Carbon\Carbon::parse($sch->start_time);
            $end = \Carbon\Carbon::parse($sch->end_time);
            while ($t->copy()->addMinutes($duration)->lte($end)) {
                $s = $t->format('H:i'); $e = $t->copy()->addMinutes($duration)->format('H:i');
                if (! ($sch->break_start && $s < $sch->break_end && $e > $sch->break_start) && ! $this->hasConflict($date, $s, $e, $clinicianId, null)) {
                    $slots[] = ['start' => $s, 'end' => $e];
                }
                $t->addMinutes($sch->slot_duration);
            }
        }
        return $slots;
    }
}
