<?php
namespace Tests\Feature\Diagnostics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Diagnostics\Models\{DiagnosticTest, DiagnosticTestCategory, DiagnosticSpecimen};
use Modules\Diagnostics\Services\DiagnosticService;
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private Branch $hq; private DiagnosticTestCategory $cat; private DiagnosticTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Diagnostics')?->enable();
        $this->cat = DiagnosticTestCategory::create(['code' => 'HEME', 'name' => 'Hematology']);
        $spec = DiagnosticSpecimen::create(['code' => 'BLOOD', 'name' => 'Blood', 'container' => 'EDTA']);
        $this->test = app(DiagnosticService::class)->createTest(['code' => 'FBC', 'name' => 'Full Blood Count', 'diagnostic_test_category_id' => $this->cat->id, 'diagnostic_specimen_id' => $spec->id, 'test_type' => 'laboratory'], $this->admin->id);
    }

    public function test_test_created() { $this->assertEquals('FBC', $this->test->code); $this->assertEquals(1, $this->test->version); }
    public function test_duplicate_code_rejected() { $this->expectException(\Illuminate\Database\QueryException::class); DiagnosticTest::create(['code' => 'FBC', 'name' => 'x', 'diagnostic_test_category_id' => $this->cat->id]); }
    public function test_update_bumps_version() { $t = app(DiagnosticService::class)->updateTest($this->test, ['name' => 'FBC v2'], $this->admin->id); $this->assertEquals(2, $t->version); }
    public function test_parameters() { $svc = app(DiagnosticService::class); $svc->addParameter($this->test, ['code' => 'WBC', 'name' => 'White Cells', 'data_type' => 'numeric', 'unit' => 'x10^9/L'], $this->admin->id); $svc->addParameter($this->test, ['code' => 'HGB', 'name' => 'Hemoglobin', 'data_type' => 'numeric'], $this->admin->id); $this->assertEquals(2, $this->test->parameters()->count()); }
    public function test_duplicate_param_rejected() { $svc = app(DiagnosticService::class); $svc->addParameter($this->test, ['code' => 'WBC', 'name' => 'x'], $this->admin->id); $this->expectException(\Illuminate\Database\QueryException::class); $svc->addParameter($this->test, ['code' => 'WBC', 'name' => 'y'], $this->admin->id); }
    public function test_reference_range_versioning()
    {
        $svc = app(DiagnosticService::class);
        $p = $svc->addParameter($this->test, ['code' => 'HGB', 'name' => 'Hgb'], $this->admin->id);
        $r1 = $svc->setReferenceRange($p, ['low' => 12, 'high' => 16, 'effective_from' => '2026-01-01'], $this->admin->id);
        $r2 = $svc->setReferenceRange($p, ['low' => 13, 'high' => 17, 'effective_from' => '2026-07-01'], $this->admin->id);
        $this->assertEquals('2026-07-01', $r1->fresh()->effective_to->toDateString());
        $this->assertNull($r2->effective_to);
    }
    public function test_branch_availability()
    {
        $svc = app(DiagnosticService::class);
        $this->assertFalse($svc->availableAt($this->test, $this->hq->id));
        $svc->enableAtBranch($this->test, $this->hq->id, $this->admin->id);
        $this->assertTrue($svc->availableAt($this->test, $this->hq->id));
    }
    public function test_inactive_unavailable()
    {
        $svc = app(DiagnosticService::class);
        $svc->enableAtBranch($this->test, $this->hq->id, $this->admin->id);
        $this->test->update(['active' => false]);
        $this->assertFalse($svc->availableAt($this->test->fresh(), $this->hq->id));
    }
}
