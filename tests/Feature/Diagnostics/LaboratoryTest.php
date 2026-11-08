<?php
namespace Tests\Feature\Diagnostics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Diagnostics\Models\{DiagnosticTest, DiagnosticTestCategory, DiagnosticSpecimen, LaboratorySpecimen};
use Modules\Diagnostics\Services\{DiagnosticService, LaboratoryService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class LaboratoryTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $lab; private Branch $hq; private DiagnosticTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->lab = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Diagnostics')?->enable();
        $cat = DiagnosticTestCategory::create(['code' => 'HEME', 'name' => 'Heme']);
        $spec = DiagnosticSpecimen::create(['code' => 'BLOOD', 'name' => 'Blood']);
        $dx = app(DiagnosticService::class);
        $this->test = $dx->createTest(['code' => 'FBC', 'name' => 'FBC', 'diagnostic_test_category_id' => $cat->id, 'diagnostic_specimen_id' => $spec->id], $this->admin->id);
        $dx->enableAtBranch($this->test, $this->hq->id, $this->admin->id);
    }

    private function order(): \Modules\Diagnostics\Models\LaboratoryOrder
    {
        return app(LaboratoryService::class)->order(1, $this->hq->id, [$this->test->id], ['priority' => 'routine'], $this->admin->id);
    }

    public function test_order_numbered() { $o = $this->order(); $this->assertMatchesRegularExpression('/^LAB-\d{4}-\d{6}$/', $o->order_number); }
    public function test_order_items_snapshot_version() { $o = $this->order(); $this->assertEquals(1, $o->items()->first()->test_version); }
    public function test_specimen_auto_created() { $o = $this->order(); $this->assertEquals(1, LaboratorySpecimen::count()); }
    public function test_unavailable_test_rejected() { $this->test->update(['active' => false]); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); $this->order(); }
    public function test_order_workflow()
    {
        $svc = app(LaboratoryService::class);
        $o = $this->order();
        $svc->transition($o, 'submitted', $this->admin->id);
        $svc->transition($o->fresh(), 'approved', $this->lab->id);
        $svc->transition($o->fresh(), 'awaiting_collection', $this->admin->id);
        $this->assertEquals('awaiting_collection', $o->fresh()->status);
    }
    public function test_self_approval_rejected()
    {
        $svc = app(LaboratoryService::class);
        $o = $this->order();
        $o->update(['status' => 'submitted']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transition($o->fresh(), 'approved', $this->admin->id);
    }
    public function test_collect_and_accession()
    {
        $svc = app(LaboratoryService::class);
        $o = $this->order();
        $o->update(['status' => 'awaiting_collection']);
        $s = LaboratorySpecimen::first();
        $svc->collect($s, $this->lab->id);
        $this->assertEquals('collected', $s->fresh()->status);
        $a = $svc->accession($s->fresh(), $this->lab->id);
        $this->assertMatchesRegularExpression('/^ACC-\d{4}-\d{6}$/', $a->accession_number);
        $this->assertEquals('accepted', $s->fresh()->status);
    }
    public function test_reject_spawns_recollection()
    {
        $svc = app(LaboratoryService::class);
        $o = $this->order();
        $o->update(['status' => 'awaiting_collection']);
        $s = LaboratorySpecimen::first();
        $svc->collect($s, $this->lab->id);
        $recall = $svc->reject($s->fresh(), 'Hemolyzed', $this->lab->id);
        $this->assertEquals('rejected', $s->fresh()->status);
        $this->assertEquals('pending', $recall->status);
        $this->assertEquals($s->id, $recall->recalled_from_id);
        $this->assertEquals(2, LaboratorySpecimen::count()); // original preserved + new recollection
    }
    public function test_collect_requires_awaiting_order()
    {
        $o = $this->order(); // draft
        $s = LaboratorySpecimen::first();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LaboratoryService::class)->collect($s, $this->lab->id);
    }
}
