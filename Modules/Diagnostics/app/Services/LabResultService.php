<?php
namespace Modules\Diagnostics\Services;
use Illuminate\Support\Facades\DB;
use Modules\Diagnostics\Models\{LaboratoryAliquot, LaboratoryCriticalResult, LaboratoryProcessingEvent, LaboratoryTestResult, LaboratorySpecimen};
use Modules\Foundation\Support\Audit;

class LabResultService
{
    public const TRANSITIONS = [
        'entered' => ['pending_verification'],
        'pending_verification' => ['verified', 'returned'],
        'verified' => ['authorized'],
        'returned' => ['entered'],
        'authorized' => ['released'],
        'released' => [], 'cancelled' => [],
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

    /** Process an accepted specimen — only 'accepted' or 'processing' allowed. */
    public function process(LaboratorySpecimen $s, string $event, int $userId, ?int $equipmentId = null): LaboratoryProcessingEvent
    {
        abort_unless(in_array($s->status, ['accepted', 'processing']), 422, "Cannot process {$s->status} specimen.");
        $e = LaboratoryProcessingEvent::create(['laboratory_specimen_id' => $s->id, 'event' => $event, 'equipment_id' => $equipmentId, 'performed_by' => $userId]);
        $s->update(['status' => 'processing']);
        Audit::record('dx.processing', null, null, ['spc' => $s->specimen_number, 'event' => $event]);
        return $e;
    }

    public function aliquot(LaboratorySpecimen $s, ?float $volume, int $userId): LaboratoryAliquot
    {
        abort_unless($s->status === 'accepted', 422);
        $a = LaboratoryAliquot::create(['aliquot_number' => $this->num('ALQ', 'laboratory_aliquots', 'aliquot_number'), 'laboratory_specimen_id' => $s->id, 'volume' => $volume, 'status' => 'stored', 'created_by' => $userId]);
        Audit::record('dx.aliquot', null, null, ['alq' => $a->aliquot_number]);
        return $a;
    }

    /** Enter results — validates type per M1 parameter, applies range flag. */
    public function enter(\Modules\Diagnostics\Models\LaboratoryOrderItem $item, array $values, int $userId): LaboratoryTestResult
    {
        $item->load('test.parameters');
        $params = $item->test->parameters->keyBy('id');
        $result = LaboratoryTestResult::create(['result_number' => $this->num('RES', 'laboratory_test_results', 'result_number'), 'laboratory_order_item_id' => $item->id, 'laboratory_specimen_id' => $item->specimens()->where('status', 'accepted')->first()?->id, 'status' => 'entered', 'entered_by' => $userId]);
        foreach ($values as $paramId => $raw) {
            $p = $params[$paramId] ?? abort(422, "Unknown parameter {$paramId}.");
            match ($p->data_type) {
                'numeric' => abort_unless(is_numeric($raw), 422, "Expected numeric for {$p->code}."),
                'boolean' => abort_unless(in_array($raw, ['true', 'false', true, false], true), 422),
                'categorical' => null, // any string allowed
                default => null,
            };
            $flag = $this->flag($p, $raw);
            $range = $p->ranges()->where('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()))->first();
            $v = $result->values()->create(['diagnostic_test_parameter_id' => $p->id, 'value' => (string) $raw, 'flag' => $flag, 'diagnostic_reference_range_id' => $range?->id]);
            if ($flag === 'critical_high' || $flag === 'critical_low') {
                LaboratoryCriticalResult::create(['laboratory_test_result_id' => $result->id, 'laboratory_result_value_id' => $v->id, 'status' => 'pending']);
            }
        }
        Audit::record('dx.result_entered', null, null, ['res' => $result->result_number]);
        return $result;
    }

    private function flag(\Modules\Diagnostics\Models\DiagnosticTestParameter $p, mixed $raw): ?string
    {
        $range = $p->ranges()->where('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()))->first();
        if (! $range || ! is_numeric($raw)) { return null; }
        if ($range->low !== null && bccomp((string) $raw, (string) $range->low, 4) < 0) { return 'critical_low'; }
        if ($range->high !== null && bccomp((string) $raw, (string) $range->high, 4) > 0) { return 'critical_high'; }
        return 'normal';
    }

    public function transition(LaboratoryTestResult $r, string $to, int $userId): LaboratoryTestResult
    {
        abort_unless(in_array($to, static::TRANSITIONS[$r->status] ?? []), 422, "Cannot {$r->status}→{$to}.");
        abort_if($to === 'verified' && $r->entered_by === $userId, 422, 'Cannot verify own result.');
        $r->update(['status' => $to] + ($to === 'verified' ? ['verified_by' => $userId] : []));
        Audit::record("dx.result_{$to}", null, null, ['res' => $r->result_number]);
        return $r->fresh();
    }

    public function acknowledgeCritical(LaboratoryCriticalResult $c, int $userId): LaboratoryCriticalResult
    {
        abort_unless($c->status === 'pending', 422);
        $c->update(['status' => 'acknowledged', 'acknowledged_by' => $userId, 'acknowledged_at' => now()]);
        Audit::record('dx.critical_acknowledged', null, null, ['cr' => $c->id]);
        return $c->fresh();
    }
}
