<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\Admission;
use Modules\HospitalOperations\Models\Bed;

class IpdService
{
    public const ADM_TRANSITIONS = [
        'draft' => ['pending_approval', 'approved', 'cancelled'],
        'pending_approval' => ['approved', 'cancelled'],
        'approved' => ['awaiting_bed', 'admitted', 'cancelled'],
        'awaiting_bed' => ['admitted', 'cancelled'],
        'admitted' => ['on_hold', 'transferred', 'discharge_pending', 'discharged'],
        'on_hold' => ['admitted', 'discharge_pending'],
        'transferred' => ['admitted', 'discharge_pending'],
        'discharge_pending' => ['discharged', 'admitted'],
        'discharged' => [], 'cancelled' => [],
    ];

    public function nextNumber(): string
    {
        return DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'ops.admission_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = Admission::where('admission_number', 'like', 'ADM-'.date('Y').'-%')->orderByDesc('admission_number')->value('admission_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => 'ops.admission_sequence'], ['group' => 'ops', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf('ADM-%s-%06d', date('Y'), $seq);
        });
    }

    public function createAdmission(array $data, int $userId): Admission
    {
        $a = Admission::create($data + ['admission_number' => $this->nextNumber(), 'status' => 'draft', 'created_by' => $userId]);
        $a->statusHistories()->create(['to_status' => 'draft', 'changed_by' => $userId]);
        Audit::record('admission.created', $a->patient, null, ['adm' => $a->admission_number], $a->branch_id);
        return $a;
    }

    public function transition(Admission $a, string $to, int $userId, ?string $reason = null): Admission
    {
        $from = $a->status;
        abort_unless(in_array($to, static::ADM_TRANSITIONS[$from] ?? []), 422, "Cannot move {$from}→{$to}.");
        abort_if(in_array($to, ['cancelled']) && ! $reason, 422, 'Reason required.');
        DB::transaction(function () use ($a, $to, $from, $userId, $reason) {
            $a->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
            $a->update(['status' => $to]);
        });
        Audit::record("admission.{$to}", $a->patient, ['status' => $from], ['status' => $to], $a->branch_id);
        return $a->fresh();
    }

    /** Claim-safe reservation: only if bed still available. */
    public function reserveBed(Bed $bed, int $userId): bool
    {
        abort_unless($bed->status === 'available', 422, 'Bed not available.');
        return (bool) Bed::where('id', $bed->id)->where('status', 'available')->update(['status' => 'reserved']);
    }

    /** Allocate a reserved/available bed to an admission — atomic. */
    public function allocateBed(Admission $a, Bed $bed, int $userId): void
    {
        abort_unless(in_array($a->status, ['approved', 'awaiting_bed']), 422, 'Admission must be approved.');
        abort_unless($bed->branch_id === $a->branch_id, 422, 'Bed is in another branch.');
        DB::transaction(function () use ($a, $bed, $userId) {
            $claimed = Bed::where('id', $bed->id)->whereIn('status', ['available', 'reserved'])->update(['status' => 'occupied']);
            abort_unless($claimed, 422, 'Bed already taken.');
            // release any previous allocation on this admission
            $a->allocations()->whereNull('released_at')->update(['released_at' => now()]);
            $a->allocations()->create(['bed_id' => $bed->id, 'ward_id' => $bed->ward_id, 'room_id' => $bed->room_id, 'allocated_at' => now(), 'allocated_by' => $userId]);
            $a->update(['bed_id' => $bed->id, 'ward_id' => $bed->ward_id, 'room_id' => $bed->room_id, 'status' => 'admitted', 'admitted_at' => now()]);
            $a->statusHistories()->create(['from_status' => 'awaiting_bed', 'to_status' => 'admitted', 'changed_by' => $userId]);
        });
        Audit::record('admission.bed_allocated', $a->patient, null, ['bed' => $bed->code], $a->branch_id);
    }

    /** Transfer to a new bed — atomic: reserve dest, release source→cleaning, alloc. */
    public function transfer(Admission $a, Bed $toBed, int $userId, ?string $reason = null): void
    {
        abort_unless($a->status === 'admitted' || $a->status === 'transferred', 422, 'Must be admitted.');
        abort_unless($toBed->status === 'available', 422, 'Destination bed not available.');
        DB::transaction(function () use ($a, $toBed, $userId, $reason) {
            $claimed = Bed::where('id', $toBed->id)->where('status', 'available')->update(['status' => 'occupied']);
            abort_unless($claimed, 422, 'Destination already taken.');
            if ($fromBed = $a->bed) {
                Bed::where('id', $fromBed->id)->update(['status' => 'cleaning', 'cleaning_since' => now()]);
            }
            $a->allocations()->whereNull('released_at')->update(['released_at' => now()]);
            $a->transfers()->create(['from_bed_id' => $a->bed_id, 'to_bed_id' => $toBed->id, 'from_ward_id' => $a->ward_id, 'to_ward_id' => $toBed->ward_id, 'reason' => $reason, 'requested_by' => $userId, 'completed_at' => now()]);
            $a->allocations()->create(['bed_id' => $toBed->id, 'ward_id' => $toBed->ward_id, 'room_id' => $toBed->room_id, 'allocated_at' => now(), 'allocated_by' => $userId]);
            $a->update(['bed_id' => $toBed->id, 'ward_id' => $toBed->ward_id, 'room_id' => $toBed->room_id, 'status' => 'admitted']);
        });
        Audit::record('admission.transferred', $a->patient, null, ['bed' => $toBed->code], $a->branch_id);
    }

    public function discharge(Admission $a, string $type, ?string $reason, int $userId): void
    {
        abort_unless(in_array($a->status, ['admitted', 'discharge_pending', 'on_hold']), 422, 'Cannot discharge in current status.');
        DB::transaction(function () use ($a, $type, $reason, $userId) {
            if ($bed = $a->bed) {
                Bed::where('id', $bed->id)->update(['status' => 'cleaning', 'cleaning_since' => now()]);
            }
            $a->allocations()->whereNull('released_at')->update(['released_at' => now()]);
            $a->update(['status' => 'discharged', 'discharge_type' => $type, 'discharge_reason' => $reason, 'discharged_at' => now(), 'discharge_approved_by' => $userId, 'bed_id' => null]);
            $a->statusHistories()->create(['from_status' => 'admitted', 'to_status' => 'discharged', 'reason' => $reason, 'changed_by' => $userId]);
        });
        Audit::record('admission.discharged', $a->patient, null, ['type' => $type], $a->branch_id);
    }

    public function completeCleaning(Bed $bed, int $userId): void
    {
        abort_unless($bed->status === 'cleaning', 422);
        $bed->update(['status' => 'available', 'cleaning_since' => null]);
        Audit::record('bed.cleaning_completed', null, null, ['bed' => $bed->code], $bed->branch_id);
    }

    /** Census for a branch. */
    public function census(int $branchId): array
    {
        $beds = Bed::where('branch_id', $branchId);
        return [
            'total' => (clone $beds)->count(),
            'available' => (clone $beds)->where('status', 'available')->count(),
            'occupied' => (clone $beds)->where('status', 'occupied')->count(),
            'reserved' => (clone $beds)->where('status', 'reserved')->count(),
            'cleaning' => (clone $beds)->where('status', 'cleaning')->count(),
            'admitted' => Admission::where('branch_id', $branchId)->where('status', 'admitted')->count(),
            'awaiting_bed' => Admission::where('branch_id', $branchId)->where('status', 'awaiting_bed')->count(),
        ];
    }
}
