<?php

namespace Modules\Administration\Services;

use Illuminate\Support\Facades\DB;
use Modules\Administration\Models\CandidateInterview;
use Modules\Administration\Models\Employee;
use Modules\Administration\Models\JobCandidate;
use Modules\Administration\Models\JobVacancy;
use Modules\Administration\Models\TrainingEnrollment;
use Modules\Foundation\Support\Audit;

class RecruitmentService
{
    public const CANDIDATE_TRANSITIONS = [
        'applied' => ['screening', 'rejected'],
        'screening' => ['interview', 'rejected'],
        'interview' => ['offered', 'rejected'],
        'offered' => ['hired', 'rejected'],
        'hired' => [], 'rejected' => [],
    ];

    private function nextNumber(string $prefix, string $table, string $col): string
    {
        return DB::transaction(function () use ($prefix, $table, $col) {
            $key = "hr.{$prefix}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$prefix}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'hr', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $prefix, date('Y'), $seq);
        });
    }

    public function createVacancy(array $data, int $userId): JobVacancy
    {
        return JobVacancy::create($data + ['vacancy_number' => $this->nextNumber('VAC', 'job_vacancies', 'vacancy_number'), 'status' => 'draft', 'created_by' => $userId]);
    }

    public function apply(JobVacancy $v, array $data): JobCandidate
    {
        abort_unless($v->status === 'open', 422, 'Vacancy not open.');
        abort_unless($v->candidates()->where('status', '!=', 'rejected')->count() < $v->openings || true, 422); // cap is informational
        $c = $v->candidates()->create($data + ['candidate_number' => $this->nextNumber('CND', 'job_candidates', 'candidate_number'), 'status' => 'applied']);
        Audit::record('hr.candidate_applied', null, null, ['cnd' => $c->candidate_number, 'vac' => $v->vacancy_number], $v->branch_id);
        return $c;
    }

    public function transitionCandidate(JobCandidate $c, string $to, int $userId): JobCandidate
    {
        abort_unless(in_array($to, static::CANDIDATE_TRANSITIONS[$c->status] ?? []), 422, "Cannot move {$c->status}→{$to}.");
        $c->update(['status' => $to]);
        Audit::record("hr.candidate_{$to}", null, null, ['cnd' => $c->candidate_number]);
        return $c->fresh();
    }

    /** Hire → creates a real Employee record + links the candidate. */
    public function hire(JobCandidate $c, array $data, int $userId): Employee
    {
        abort_unless($c->status === 'offered', 422, 'Only offered candidates can be hired.');
        return DB::transaction(function () use ($c, $data, $userId) {
            $e = app(HrService::class)->register($data + [
                'first_name' => $c->first_name, 'last_name' => $c->last_name,
                'primary_branch_id' => $c->vacancy->branch_id,
            ], $userId);
            $c->update(['status' => 'hired', 'employee_id' => $e->id]);
            $c->vacancy->decrement('openings');
            Audit::record('hr.candidate_hired', null, null, ['cnd' => $c->candidate_number, 'emp' => $e->employee_number], $e->primary_branch_id);
            return $e;
        });
    }

    public function scheduleInterview(JobCandidate $c, array $data, int $userId): CandidateInterview
    {
        abort_unless($c->status === 'interview', 422);
        return $c->interviews()->create($data + ['status' => 'scheduled', 'created_by' => $userId]);
    }

    public function enroll(Employee $e, int $courseId, int $userId): TrainingEnrollment
    {
        $en = TrainingEnrollment::firstOrCreate(
            ['training_course_id' => $courseId, 'employee_id' => $e->id],
            ['status' => 'enrolled', 'created_by' => $userId]
        );
        Audit::record('hr.training_enrolled', null, null, ['emp' => $e->employee_number]);
        return $en;
    }

    public function completeTraining(TrainingEnrollment $en, int $userId): TrainingEnrollment
    {
        abort_unless($en->status === 'enrolled', 422);
        $en->update(['status' => 'completed', 'completed_at' => today()]);
        Audit::record('hr.training_completed', null, null, ['enr' => $en->id]);
        return $en->fresh();
    }
}
