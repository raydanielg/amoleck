<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalChecklistTemplate;
use Modules\HospitalOperations\Models\SurgicalSafetyException;
use Modules\HospitalOperations\Models\SurgicalSafetyVerification;

class SurgicalSafetyService
{
    public const STAGES = ['sign_in', 'time_out', 'sign_out'];

    /** Perform a WHO-style stage verification using the active hospital-approved template. */
    public function verify(SurgicalCase $c, string $stage, array $items, int $userId, array $participantIds = [], ?string $notes = null): SurgicalSafetyVerification
    {
        abort_unless(in_array($stage, self::STAGES), 422, 'Invalid stage.');
        abort_if($c->safetyVerifications()->where('stage', $stage)->exists(), 422, "Stage {$stage} already verified.");
        // Order enforcement: sign_in → time_out → sign_out
        $order = array_flip(self::STAGES);
        if ($order[$stage] > 0) {
            $prev = self::STAGES[$order[$stage] - 1];
            abort_unless($c->safetyVerifications()->where('stage', $prev)->exists(), 422, "Stage {$prev} must be completed first.");
        }
        $tpl = SurgicalChecklistTemplate::where('stage', $stage)->where('active', true)->latest('version')->first();
        $exceptions = $c->safetyExceptions()->where('stage', $stage)->get()->keyBy('item_key');

        $missing = [];
        $rows = [];
        $provided = collect($items)->keyBy('key');
        foreach (($tpl?->items ?? []) as $item) {
            $key = $item['key'];
            $given = $provided->get($key);
            $checked = (bool) ($given['checked'] ?? false);
            if (($item['mandatory'] ?? false) && ! $checked && ! $exceptions->has($key)) {
                $missing[] = $key;
            }
            $rows[] = [
                'item_key' => $key, 'label' => $item['label'] ?? $key,
                'checked' => $checked, 'value' => $given['value'] ?? null,
                'checked_by' => $checked ? $userId : null, 'notes' => $given['notes'] ?? null,
            ];
        }
        abort_if($missing, 422, 'Mandatory checks incomplete: '.implode(', ', $missing));

        return DB::transaction(function () use ($c, $stage, $rows, $userId, $participantIds, $notes, $exceptions) {
            $v = $c->safetyVerifications()->create([
                'stage' => $stage, 'status' => $exceptions->isNotEmpty() ? 'exception' : 'completed',
                'performed_by' => $userId, 'performed_at' => now(),
                'participant_ids' => $participantIds, 'notes' => $notes,
            ]);
            $v->items()->createMany($rows);
            Audit::record("surgery.safety_{$stage}", $c->patient, null, ['items' => count($rows)], $c->branch_id);
            return $v->fresh('items');
        });
    }

    /** Documented, authorized exception for a mandatory check (e.g. emergency). */
    public function recordException(SurgicalCase $c, string $stage, string $itemKey, string $reason, int $authorizedBy, int $userId): SurgicalSafetyException
    {
        abort_unless(in_array($stage, self::STAGES), 422);
        abort_if($c->safetyExceptions()->where('stage', $stage)->where('item_key', $itemKey)->exists(), 422, 'Exception already recorded.');
        $e = SurgicalSafetyException::create([
            'surgical_case_id' => $c->id, 'stage' => $stage, 'item_key' => $itemKey,
            'reason' => $reason, 'authorized_by' => $authorizedBy, 'created_by' => $userId,
        ]);
        Audit::record('surgery.safety_exception', $c->patient, null, ['stage' => $stage, 'item' => $itemKey], $c->branch_id);
        return $e;
    }

    public function stageDone(SurgicalCase $c, string $stage): bool
    {
        return $c->safetyVerifications()->where('stage', $stage)->exists();
    }
}
