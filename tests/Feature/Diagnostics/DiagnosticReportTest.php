<?php
namespace Tests\Feature\Diagnostics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Diagnostics\Models\{DiagnosticTest, DiagnosticTestCategory, DiagnosticSpecimen, LaboratorySpecimen, DiagnosticReport};
use Modules\Diagnostics\Services\{DiagnosticService, LaboratoryService, LabResultService, DiagnosticReportService};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class DiagnosticReportTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $tech; private User $verifier; private User $authorizer; private $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->tech = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->verifier = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->authorizer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Diagnostics')?->enable();
        $cat = DiagnosticTestCategory::create(['code' => 'H', 'name' => 'H']);
        $spec = DiagnosticSpecimen::create(['code' => 'B', 'name' => 'B']);
        $dx = app(DiagnosticService::class);
        $t = $dx->createTest(['code' => 'FBC', 'name' => 'FBC', 'diagnostic_test_category_id' => $cat->id, 'diagnostic_specimen_id' => $spec->id], $this->admin->id);
        $dx->enableAtBranch($t, $hq->id, $this->admin->id);
        $p = $dx->addParameter($t, ['code' => 'WBC', 'name' => 'WBC', 'data_type' => 'numeric'], $this->admin->id);
        $o = app(LaboratoryService::class)->order(1, $hq->id, [$t->id], [], $this->admin->id);
        $o->update(['status' => 'awaiting_collection']);
        $s = LaboratorySpecimen::first();
        app(LaboratoryService::class)->collect($s, $this->tech->id);
        app(LaboratoryService::class)->accession($s->fresh(), $this->tech->id);
        $this->item = $o->items()->first();
    }

    private function verifiedResult()
    {
        $svc = app(LabResultService::class);
        $r = $svc->enter($this->item, [DiagnosticTest::first()->parameters()->first()->id => 5.0], $this->tech->id);
        $r->update(['status' => 'verified', 'verified_by' => $this->verifier->id]);
        return $r->fresh();
    }

    public function test_authorize_requires_verified()
    {
        $r = app(LabResultService::class)->enter($this->item, [DiagnosticTest::first()->parameters()->first()->id => 5.0], $this->tech->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DiagnosticReportService::class)->authorize($r, $this->authorizer->id); // entered, not verified
    }

    public function test_release_creates_report()
    {
        $r = $this->verifiedResult();
        app(DiagnosticReportService::class)->authorize($r, $this->authorizer->id);
        $report = app(DiagnosticReportService::class)->release($r->fresh(), $this->authorizer->id);
        $this->assertMatchesRegularExpression('/^DGR-\d{4}-\d{6}$/', $report->report_number);
        $this->assertEquals('released', $report->status);
        $this->assertEquals('released', $r->fresh()->status);
    }

    public function test_unauthorized_cannot_release()
    {
        $r = $this->verifiedResult(); // verified, not authorized
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DiagnosticReportService::class)->release($r, $this->authorizer->id);
    }

    public function test_self_authorization_rejected()
    {
        $r = $this->verifiedResult(); // verified_by = verifier
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DiagnosticReportService::class)->authorize($r, $this->verifier->id); // same person
    }

    public function test_amend_supersedes_original()
    {
        $r = $this->verifiedResult();
        $svc = app(DiagnosticReportService::class);
        $svc->authorize($r, $this->authorizer->id);
        $orig = $svc->release($r->fresh(), $this->authorizer->id);
        $new = $svc->amend($orig, $this->authorizer->id);
        $this->assertEquals('superseded', $orig->fresh()->status);
        $this->assertEquals(2, $new->version);
        $this->assertEquals($orig->id, $new->supersedes_id);
        $this->assertEquals(2, DiagnosticReport::count());
    }

    public function test_clinician_review()
    {
        $r = $this->verifiedResult();
        $svc = app(DiagnosticReportService::class);
        $svc->authorize($r, $this->authorizer->id);
        $report = $svc->release($r->fresh(), $this->authorizer->id);
        $review = $svc->review($report, $this->admin->id, 'Reviewed', 'Follow-up in 2 weeks');
        $this->assertEquals($report->id, $review->diagnostic_report_id);
    }
}
