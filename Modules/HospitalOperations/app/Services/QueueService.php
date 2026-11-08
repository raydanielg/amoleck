<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\PatientVisit;
use Modules\HospitalOperations\Models\QueueEntry;

class QueueService
{
    public const VISIT_TRANSITIONS = [
        'expected' => ['arrived', 'cancelled', 'no_show'],
        'arrived' => ['registration_pending', 'checked_in', 'cancelled'],
        'registration_pending' => ['checked_in', 'cancelled'],
        'checked_in' => ['waiting', 'cancelled'],
        'waiting' => ['called', 'on_hold', 'cancelled'],
        'called' => ['in_service', 'on_hold', 'waiting'],
        'in_service' => ['completed', 'referred', 'on_hold'],
        'on_hold' => ['waiting', 'cancelled'],
        'referred' => [], 'completed' => [], 'cancelled' => [], 'no_show' => [],
    ];

    public const PRIORITIES = ['emergency' => 0, 'urgent' => 1, 'high' => 2, 'normal' => 3, 'low' => 4];
    public const SOURCES = ['walk_in', 'appointment', 'internal_referral', 'external_referral', 'follow_up'];

    private function nextNumber(string $prefix, string $table, string $col, int $width = 4): string
    {
        return DB::transaction(function () use ($prefix, $table, $col, $width) {
            $key = "ops.{$prefix}_sequence";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -$width)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'ops', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%0{$width}d", $prefix, date('Y'), $seq);
        });
    }

    /** Create visit + enqueue in one transaction. */
    public function createVisit(array $data, int $userId): PatientVisit
    {
        return DB::transaction(function () use ($data, $userId) {
            $visit = PatientVisit::create(array_diff_key($data, array_flip(['queue_type_id', 'priority'])) + [
                'visit_number' => $this->nextNumber('VIS', 'patient_visits', 'visit_number', 6),
                'status' => 'arrived', 'arrived_at' => now(), 'created_by' => $userId,
            ]);
            $visit->queueEntries()->create([
                'ticket_number' => $this->nextNumber('QT', 'queue_entries', 'ticket_number'),
                'queue_type_id' => $data['queue_type_id'], 'branch_id' => $visit->branch_id,
                'priority' => $data['priority'] ?? 'normal', 'status' => 'waiting',
            ])->statusHistories()->create(['to_status' => 'waiting', 'changed_by' => $userId]);
            Audit::record('queue.visit_arrived', $visit->patient, null, ['visit' => $visit->visit_number, 'source' => $visit->source], $visit->branch_id);
            return $visit->fresh();
        });
    }

    public function transitionVisit(PatientVisit $visit, string $to, int $userId, ?string $reason = null): PatientVisit
    {
        $from = $visit->status;
        abort_unless(in_array($to, static::VISIT_TRANSITIONS[$from] ?? []), 422, "Cannot move visit {$from}→{$to}.");
        abort_if(in_array($to, ['cancelled', 'no_show']) && ! $reason, 422, 'Reason required.');
        DB::transaction(function () use ($visit, $to, $from, $userId, $reason) {
            $visit->update(['status' => $to] + match ($to) {
                'checked_in' => ['checked_in_at' => now()],
                'in_service' => ['service_started_at' => now()],
                'completed' => ['completed_at' => now()],
                default => [],
            });
            if ($entry = $visit->activeEntry()) {
                $map = ['waiting' => 'waiting', 'called' => 'called', 'in_service' => 'in_service', 'completed' => 'completed', 'cancelled' => 'removed', 'on_hold' => 'hold'];
                if (isset($map[$to])) { $this->setEntryStatus($entry, $map[$to], $userId, $reason); }
            }
        });
        Audit::record("queue.visit_{$to}", $visit->patient, ['status' => $from], ['status' => $to], $visit->branch_id);
        return $visit->fresh();
    }

    private function setEntryStatus(QueueEntry $e, string $to, int $userId, ?string $reason = null): void
    {
        $e->statusHistories()->create(['from_status' => $e->status, 'to_status' => $to, 'reason' => $reason, 'changed_by' => $userId]);
        $e->update(['status' => $to]);
    }

    /** Claim-safe call: only succeeds if entry is still waiting/called. */
    public function call(QueueEntry $e, int $userId): bool
    {
        $claimed = QueueEntry::where('id', $e->id)->whereIn('status', ['waiting', 'called', 'hold'])->update(['status' => 'called', 'called_at' => now(), 'called_by' => $userId]);
        if ($claimed) {
            $e->statusHistories()->create(['to_status' => 'called', 'changed_by' => $userId]);
            $e->visit->update(['status' => 'called']); // keep visit + entry in sync
            Audit::record('queue.called', $e->visit->patient, null, ['ticket' => $e->ticket_number], $e->branch_id);
        }
        return (bool) $claimed;
    }

    public function callNext(int $queueTypeId, int $userId): ?QueueEntry
    {
        $order = implode("', '", array_keys(static::PRIORITIES));
        $e = QueueEntry::where('queue_type_id', $queueTypeId)->where('status', 'waiting')
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 0 WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderBy('created_at')->first();
        return $e && $this->call($e, $userId) ? $e->fresh() : null;
    }

    public function skip(QueueEntry $e, int $userId, string $reason): void
    {
        abort_unless($e->status === 'waiting', 422);
        $this->setEntryStatus($e, 'skipped', $userId, $reason);
    }

    public function requeue(QueueEntry $e, int $userId): void
    {
        abort_unless(in_array($e->status, ['skipped', 'hold', 'called']), 422);
        $this->setEntryStatus($e, 'waiting', $userId, 'Requeued');
    }

    public function transfer(QueueEntry $e, int $toQueueTypeId, int $userId, string $reason): void
    {
        abort_unless($e->status === 'waiting', 422, 'Only waiting entries can transfer.');
        DB::transaction(function () use ($e, $toQueueTypeId, $userId, $reason) {
            $e->transfers()->create(['from_queue_type_id' => $e->queue_type_id, 'to_queue_type_id' => $toQueueTypeId, 'reason' => $reason, 'changed_by' => $userId]);
            $e->update(['queue_type_id' => $toQueueTypeId]);
        });
        Audit::record('queue.transferred', $e->visit->patient, null, ['ticket' => $e->ticket_number], $e->branch_id);
    }

    public function setPriority(QueueEntry $e, string $priority, int $userId, ?string $reason = null): void
    {
        abort_unless(array_key_exists($priority, static::PRIORITIES), 422);
        $e->statusHistories()->create(['from_status' => $e->priority, 'to_status' => "priority:{$priority}", 'reason' => $reason, 'changed_by' => $userId]);
        $e->update(['priority' => $priority]);
        Audit::record('queue.priority_changed', $e->visit->patient, null, ['priority' => $priority], $e->branch_id);
    }

    /** Waiting-time analytics from real timestamps; invalid rows excluded. */
    public function analytics(int $queueTypeId, ?string $date = null): array
    {
        $entries = QueueEntry::where('queue_type_id', $queueTypeId)->when($date, fn ($q) => $q->whereDate('created_at', $date))->with('visit')->get();
        $waits = $entries->filter(fn ($e) => $e->called_at)->map(fn ($e) => $e->created_at->diffInMinutes($e->called_at));
        $services = $entries->filter(fn ($e) => $e->visit?->service_started_at && $e->visit?->completed_at)->map(fn ($e) => $e->visit->service_started_at->diffInMinutes($e->visit->completed_at));
        return [
            'waiting' => $entries->where('status', 'waiting')->count(),
            'called' => $entries->where('status', 'called')->count(),
            'in_service' => $entries->where('status', 'in_service')->count(),
            'completed' => $entries->where('status', 'completed')->count(),
            'avg_wait_min' => $waits->count() ? round($waits->avg(), 1) : null,
            'max_wait_min' => $waits->count() ? $waits->max() : null,
            'avg_service_min' => $services->count() ? round($services->avg(), 1) : null,
        ];
    }
}
