<?php
namespace Modules\Diagnostics\Services;
use Illuminate\Support\Facades\DB;
use Modules\Diagnostics\Models\{DiagnosticBranchTest, DiagnosticReferenceRange, DiagnosticTest, DiagnosticTestParameter};
use Modules\Foundation\Support\Audit;

class DiagnosticService
{
    public function createTest(array $data, int $userId): DiagnosticTest
    {
        $t = DiagnosticTest::create($data + ['version' => 1, 'active' => true]);
        Audit::record('dx.test_created', null, null, ['test' => $t->code]);
        return $t;
    }

    /** Catalogue change → version bump, never overwrite history. */
    public function updateTest(DiagnosticTest $t, array $data, int $userId): DiagnosticTest
    {
        $t->update($data + ['version' => $t->version + 1]);
        Audit::record('dx.test_updated', null, null, ['test' => $t->code, 'v' => $t->version]);
        return $t->fresh();
    }

    public function addParameter(DiagnosticTest $t, array $data, int $userId): DiagnosticTestParameter
    {
        $p = $t->parameters()->create($data);
        Audit::record('dx.param_added', null, null, ['test' => $t->code, 'param' => $p->code]);
        return $p;
    }

    /** Versioned reference range — closes the previous effective range. */
    public function setReferenceRange(DiagnosticTestParameter $p, array $data, int $userId): DiagnosticReferenceRange
    {
        return DB::transaction(function () use ($p, $data, $userId) {
            DiagnosticReferenceRange::where('diagnostic_test_parameter_id', $p->id)->whereNull('effective_to')->update(['effective_to' => $data['effective_from']]);
            $r = DiagnosticReferenceRange::create($data + ['diagnostic_test_parameter_id' => $p->id]);
            Audit::record('dx.range_set', null, null, ['param' => $p->code, 'from' => $data['effective_from']]);
            return $r;
        });
    }

    public function enableAtBranch(DiagnosticTest $t, int $branchId, int $userId): DiagnosticBranchTest
    {
        $b = DiagnosticBranchTest::updateOrCreate(['diagnostic_test_id' => $t->id, 'branch_id' => $branchId], ['active' => true]);
        Audit::record('dx.branch_enabled', null, null, ['test' => $t->code, 'branch' => $branchId], $branchId);
        return $b;
    }

    public function availableAt(DiagnosticTest $t, int $branchId): bool
    {
        return $t->active && $t->branches()->where('branch_id', $branchId)->where('active', true)->exists();
    }
}
