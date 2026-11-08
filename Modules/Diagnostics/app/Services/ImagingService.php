<?php
namespace Modules\Diagnostics\Services;
use Illuminate\Support\Facades\DB;
use Modules\Diagnostics\Models\{ImagingProcedure, ImagingRequest, ImagingStudy, RadiologyReport};
use Modules\Foundation\Support\Audit;

class ImagingService
{
    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected'],
        'approved' => ['scheduled'],
        'scheduled' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'failed'],
        'completed' => ['reported'],
        'rejected' => [], 'cancelled' => [], 'failed' => [], 'reported' => [],
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

    public function request(int $patientId, int $branchId, int $testId, array $data, int $userId): ImagingRequest
    {
        $r = ImagingRequest::create($data + ['request_number' => $this->num('IMG', 'imaging_requests', 'request_number'), 'patient_id' => $patientId, 'branch_id' => $branchId, 'diagnostic_test_id' => $testId, 'status' => 'requested', 'ordered_by' => $userId]);
        Audit::record('dx.imaging_requested', null, null, ['img' => $r->request_number], $branchId);
        return $r;
    }

    public function transition(ImagingRequest $r, string $to, int $userId, ?string $reason = null): ImagingRequest
    {
        abort_unless(in_array($to, static::TRANSITIONS[$r->status] ?? []), 422, "Cannot {$r->status}→{$to}.");
        abort_if($to === 'rejected' && ! $reason, 422, 'Reason required.');
        abort_if($to === 'approved' && $r->ordered_by === $userId, 422, 'Cannot approve own request.');
        $r->update(['status' => $to] + match ($to) {
            'approved', 'rejected' => ['reviewed_by' => $userId, 'reject_reason' => $reason],
            default => [],
        });
        Audit::record("dx.imaging_{$to}", null, null, ['img' => $r->request_number]);
        return $r->fresh();
    }

    /** Schedule a procedure — blocks out-of-service equipment. */
    public function schedule(ImagingRequest $r, ?int $equipmentId, string $scheduledAt, int $userId): ImagingProcedure
    {
        abort_unless($r->status === 'approved', 422);
        if ($equipmentId) {
            $eq = \Modules\Inventory\Models\Equipment::findOrFail($equipmentId);
            abort_if(in_array($eq->status, ['out_of_service', 'quarantined', 'damaged', 'retired']), 422, "Equipment {$eq->status}.");
        }
        $p = ImagingProcedure::create(['imaging_request_id' => $r->id, 'equipment_id' => $equipmentId, 'status' => 'scheduled', 'scheduled_at' => $scheduledAt]);
        $r->update(['status' => 'scheduled']);
        Audit::record('dx.procedure_scheduled', null, null, ['img' => $r->request_number]);
        return $p;
    }

    /** Acquire a study — only completed procedures. */
    public function acquireStudy(ImagingProcedure $p): ImagingStudy
    {
        $p->update(['status' => 'completed', 'completed_at' => now()]);
        return ImagingStudy::create(['study_uid' => 'STU-'.strtoupper(uniqid()), 'imaging_procedure_id' => $p->id, 'status' => 'acquired']);
    }

    /** Draft a report — only acquired studies. */
    public function draftReport(ImagingRequest $r, array $data, int $userId): RadiologyReport
    {
        abort_unless($r->status === 'reported' || $r->procedures()->where('status', 'completed')->exists(), 422);
        return RadiologyReport::create($data + ['report_number' => $this->num('RAD', 'radiology_reports', 'report_number'), 'imaging_request_id' => $r->id, 'imaging_study_id' => $r->procedures()->where('status', 'completed')->first()?->study?->id, 'status' => 'draft', 'radiologist_id' => $userId, 'version' => 1]);
    }

    /** Authorize — self-block. */
    public function authorizeReport(RadiologyReport $rep, int $userId): RadiologyReport
    {
        abort_unless($rep->status === 'draft', 422);
        abort_if($rep->radiologist_id === $userId, 422, 'Cannot authorize own report.');
        $rep->update(['status' => 'authorized', 'authorized_by' => $userId, 'authorized_at' => now()]);
        Audit::record('dx.rad_authorized', null, null, ['rad' => $rep->report_number]);
        return $rep->fresh();
    }
}
