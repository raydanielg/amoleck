<?php
namespace Modules\Diagnostics\Services;
use Illuminate\Support\Facades\DB;
use Modules\Diagnostics\Models\{DiagnosticTest, LaboratoryAccession, LaboratoryOrder, LaboratorySpecimen};
use Modules\Foundation\Support\Audit;

class LaboratoryService
{
    public const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'rejected'],
        'approved' => ['awaiting_collection'],
        'awaiting_collection' => ['collected'],
        'collected' => ['processing'],
        'processing' => ['completed'],
        'rejected' => [], 'cancelled' => [], 'completed' => [],
    ];

    private function num(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "dx.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'dx', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    /** Order — validates tests are active + branch-available, snapshots version. */
    public function order(int $patientId, int $branchId, array $testIds, array $data, int $userId): LaboratoryOrder
    {
        abort_unless(count($testIds) > 0, 422, 'No tests.');
        $tests = DiagnosticTest::whereIn('id', $testIds)->get();
        abort_unless($tests->count() === count($testIds), 422, 'Unknown test.');
        foreach ($tests as $t) {
            abort_unless(app(DiagnosticService::class)->availableAt($t, $branchId), 422, "{$t->code} not available.");
        }
        return DB::transaction(function () use ($patientId, $branchId, $tests, $data, $userId) {
            $o = LaboratoryOrder::create($data + ['order_number' => $this->num('LAB', 'laboratory_orders', 'order_number'), 'patient_id' => $patientId, 'branch_id' => $branchId, 'status' => 'draft', 'ordered_by' => $userId]);
            foreach ($tests as $t) {
                $item = $o->items()->create(['diagnostic_test_id' => $t->id, 'test_version' => $t->version, 'status' => 'pending']);
                if ($t->diagnostic_specimen_id) {
                    $item->specimens()->create(['specimen_number' => $this->num('SPC', 'laboratory_specimens', 'specimen_number'), 'diagnostic_specimen_id' => $t->diagnostic_specimen_id, 'status' => 'pending']);
                }
            }
            Audit::record('dx.order_created', null, null, ['lab' => $o->order_number], $branchId);
            return $o;
        });
    }

    public function transition(LaboratoryOrder $o, string $to, int $userId, ?string $reason = null): LaboratoryOrder
    {
        abort_unless(in_array($to, static::TRANSITIONS[$o->status] ?? []), 422, "Cannot {$o->status}→{$to}.");
        abort_if($to === 'rejected' && ! $reason, 422, 'Reason required.');
        abort_if($to === 'approved' && $o->ordered_by === $userId, 422, 'Cannot approve own order.');
        $o->update(['status' => $to] + match ($to) {
            'approved', 'rejected' => ['reviewed_by' => $userId, 'reject_reason' => $reason],
            default => [],
        });
        Audit::record("dx.order_{$to}", null, null, ['lab' => $o->order_number], $o->branch_id);
        return $o->fresh();
    }

    /** Collect a specimen — only after order is awaiting_collection. */
    public function collect(LaboratorySpecimen $s, int $userId): LaboratorySpecimen
    {
        abort_unless($s->status === 'pending', 422);
        abort_unless($s->item->order->status === 'awaiting_collection', 422, 'Order not ready.');
        $s->update(['status' => 'collected', 'collected_by' => $userId, 'collected_at' => now()]);
        Audit::record('dx.specimen_collected', null, null, ['spc' => $s->specimen_number]);
        return $s->fresh();
    }

    /** Receive + accession — only collected specimens. */
    public function accession(LaboratorySpecimen $s, int $userId): LaboratoryAccession
    {
        abort_unless($s->status === 'collected', 422);
        return DB::transaction(function () use ($s, $userId) {
            $a = LaboratoryAccession::create(['accession_number' => $this->num('ACC', 'laboratory_accessions', 'accession_number'), 'laboratory_specimen_id' => $s->id, 'received_by' => $userId]);
            $s->update(['status' => 'accepted']);
            Audit::record('dx.accessioned', null, null, ['acc' => $a->accession_number]);
            return $a;
        });
    }

    /** Reject a specimen → spawns a recollection specimen linked to the original. */
    public function reject(LaboratorySpecimen $s, string $reason, int $userId): LaboratorySpecimen
    {
        abort_unless(in_array($s->status, ['collected', 'accepted']), 422);
        abort_unless($reason, 422);
        $s->update(['status' => 'rejected', 'reject_reason' => $reason]);
        $recall = $s->item->specimens()->create(['specimen_number' => $this->num('SPC', 'laboratory_specimens', 'specimen_number'), 'diagnostic_specimen_id' => $s->diagnostic_specimen_id, 'status' => 'pending', 'recalled_from_id' => $s->id]);
        Audit::record('dx.specimen_rejected', null, null, ['spc' => $s->specimen_number, 'reason' => $reason]);
        return $recall;
    }
}
