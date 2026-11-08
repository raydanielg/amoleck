<?php
namespace Modules\Diagnostics\Services;
use Illuminate\Support\Facades\DB;
use Modules\Diagnostics\Models\{DiagnosticClinicianReview, DiagnosticReport, LaboratoryTestResult};
use Modules\Foundation\Support\Audit;

class DiagnosticReportService
{
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

    /** Final authorization — only verified results, no self-approval. */
    public function authorize(LaboratoryTestResult $r, int $userId): LaboratoryTestResult
    {
        abort_unless($r->status === 'verified', 422, 'Must be technically verified first.');
        abort_if($r->verified_by === $userId, 422, 'Cannot authorize own verification.');
        $r->update(['status' => 'authorized']);
        Audit::record('dx.result_authorized', null, null, ['res' => $r->result_number]);
        return $r->fresh();
    }

    /** Release → creates an immutable report snapshot. */
    public function release(LaboratoryTestResult $r, int $userId): DiagnosticReport
    {
        abort_unless($r->status === 'authorized', 422);
        return DB::transaction(function () use ($r, $userId) {
            $report = DiagnosticReport::create(['report_number' => $this->num('DGR', 'diagnostic_reports', 'report_number'), 'laboratory_test_result_id' => $r->id, 'version' => 1, 'status' => 'released', 'released_by' => $userId, 'released_at' => now()]);
            $r->update(['status' => 'released']);
            Audit::record('dx.report_released', null, null, ['dgr' => $report->report_number]);
            return $report;
        });
    }

    /** Amend a released report → supersedes original + new version. */
    public function amend(DiagnosticReport $report, int $userId): DiagnosticReport
    {
        abort_unless($report->status === 'released', 422);
        return DB::transaction(function () use ($report, $userId) {
            $report->update(['status' => 'superseded']);
            $new = DiagnosticReport::create(['report_number' => $this->num('DGR', 'diagnostic_reports', 'report_number'), 'laboratory_test_result_id' => $report->laboratory_test_result_id, 'version' => $report->version + 1, 'supersedes_id' => $report->id, 'status' => 'released', 'released_by' => $userId, 'released_at' => now()]);
            Audit::record('dx.report_amended', null, null, ['dgr' => $new->report_number, 'v' => $new->version]);
            return $new;
        });
    }

    /** Clinician acknowledges a released report. */
    public function review(DiagnosticReport $report, int $userId, ?string $comments = null, ?string $followUp = null): DiagnosticClinicianReview
    {
        abort_unless($report->status === 'released', 422);
        $r = DiagnosticClinicianReview::create(['diagnostic_report_id' => $report->id, 'reviewed_by' => $userId, 'comments' => $comments, 'follow_up' => $followUp]);
        Audit::record('dx.clinician_review', null, null, ['dgr' => $report->report_number]);
        return $r;
    }
}
