<?php
namespace Tests\Feature\Diagnostics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Diagnostics\Models\{DiagnosticTest, DiagnosticTestCategory, DiagnosticSpecimen, LaboratorySpecimen, LaboratoryCriticalResult};
use Modules\Diagnostics\Services\{DiagnosticService, LaboratoryService, LabResultService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class LabResultTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $tech; private User $verifier; private $item; private $specimen; private $param;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->tech = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->verifier = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Diagnostics')?->enable();
        $cat = DiagnosticTestCategory::create(['code' => 'H', 'name' => 'H']);
        $spec = DiagnosticSpecimen::create(['code' => 'B', 'name' => 'Blood']);
        $dx = app(DiagnosticService::class);
        $t = $dx->createTest(['code' => 'GLU', 'name' => 'Glucose', 'diagnostic_test_category_id' => $cat->id, 'diagnostic_specimen_id' => $spec->id], $this->admin->id);
        $dx->enableAtBranch($t, $hq->id, $this->admin->id);
        $this->param = $dx->addParameter($t, ['code' => 'GLU', 'name' => 'Glucose', 'data_type' => 'numeric', 'unit' => 'mmol/L'], $this->admin->id);
        $dx->setReferenceRange($this->param, ['low' => 3.9, 'high' => 5.6, 'effective_from' => '2026-01-01'], $this->admin->id);
        $lab = app(LaboratoryService::class);
        $o = $lab->order(1, $hq->id, [$t->id], [], $this->admin->id);
        $o->update(['status' => 'awaiting_collection']);
        $this->specimen = LaboratorySpecimen::first();
        $lab->collect($this->specimen, $this->tech->id);
        $lab->accession($this->specimen->fresh(), $this->tech->id);
        $this->item = $o->items()->first();
    }

    public function test_processing_accepted() { $e = app(LabResultService::class)->process($this->specimen->fresh(), 'centrifuge', $this->tech->id); $this->assertEquals('centrifuge', $e->event); }
    public function test_rejected_cannot_process() { $this->specimen->update(['status' => 'rejected']); $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(LabResultService::class)->process($this->specimen->fresh(), 'x', $this->tech->id); }
    public function test_aliquot() { $a = app(LabResultService::class)->aliquot($this->specimen->fresh(), 2.5, $this->tech->id); $this->assertMatchesRegularExpression('/^ALQ-\d{4}-\d{6}$/', $a->aliquot_number); $this->assertEquals($this->specimen->id, $a->laboratory_specimen_id); }
    public function test_numeric_result_with_range_flag()
    {
        $r = app(LabResultService::class)->enter($this->item, [$this->param->id => 5.0], $this->tech->id);
        $this->assertEquals('normal', $r->values()->first()->flag);
    }
    public function test_critical_flag_creates_event()
    {
        $r = app(LabResultService::class)->enter($this->item, [$this->param->id => 15.0], $this->tech->id);
        $this->assertEquals('critical_high', $r->values()->first()->flag);
        $this->assertEquals(1, LaboratoryCriticalResult::count());
    }
    public function test_non_numeric_rejected()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LabResultService::class)->enter($this->item, [$this->param->id => 'high'], $this->tech->id);
    }
    public function test_verification_workflow()
    {
        $svc = app(LabResultService::class);
        $r = $svc->enter($this->item, [$this->param->id => 5.0], $this->tech->id);
        $svc->transition($r, 'pending_verification', $this->tech->id);
        $svc->transition($r->fresh(), 'verified', $this->verifier->id);
        $this->assertEquals('verified', $r->fresh()->status);
    }
    public function test_self_verification_rejected()
    {
        $svc = app(LabResultService::class);
        $r = $svc->enter($this->item, [$this->param->id => 5.0], $this->tech->id);
        $r->update(['status' => 'pending_verification']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->transition($r->fresh(), 'verified', $this->tech->id);
    }
    public function test_critical_acknowledgement()
    {
        $svc = app(LabResultService::class);
        $r = $svc->enter($this->item, [$this->param->id => 15.0], $this->tech->id);
        $c = LaboratoryCriticalResult::first();
        $svc->acknowledgeCritical($c, $this->admin->id);
        $this->assertEquals('acknowledged', $c->fresh()->status);
    }
}
