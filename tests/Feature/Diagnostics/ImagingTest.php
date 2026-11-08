<?php
namespace Tests\Feature\Diagnostics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Diagnostics\Models\{DiagnosticTest, DiagnosticTestCategory, ImagingRequest, RadiologyReport};
use Modules\Diagnostics\Services\{DiagnosticService, ImagingService};
use Modules\Inventory\Models\{Equipment, EquipmentCategory};
use Modules\Foundation\Models\Branch;
use Tests\TestCase;

class ImagingTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $radiologist; private User $reviewer; private Branch $hq; private DiagnosticTest $svc; private ImagingRequest $req;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->radiologist = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->reviewer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Diagnostics')?->enable();
        $cat = DiagnosticTestCategory::create(['code' => 'RAD', 'name' => 'Radiology']);
        $this->svc = app(DiagnosticService::class)->createTest(['code' => 'CXR', 'name' => 'Chest X-ray', 'diagnostic_test_category_id' => $cat->id, 'test_type' => 'imaging'], $this->admin->id);
        $this->req = app(ImagingService::class)->request(1, $this->hq->id, $this->svc->id, ['modality' => 'xray', 'body_region' => 'chest', 'clinical_indication' => 'cough'], $this->admin->id);
    }

    public function test_request_numbered() { $this->assertMatchesRegularExpression('/^IMG-\d{4}-\d{6}$/', $this->req->request_number); }
    public function test_workflow() { $svc = app(ImagingService::class); $svc->transition($this->req, 'approved', $this->reviewer->id); $this->assertEquals('approved', $this->req->fresh()->status); }
    public function test_self_approval_rejected() { $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(ImagingService::class)->transition($this->req, 'approved', $this->admin->id); }
    public function test_unapproved_cannot_schedule() { $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(ImagingService::class)->schedule($this->req, null, '2026-11-10 10:00', $this->admin->id); }
    public function test_out_of_service_equipment_blocked()
    {
        $svc = app(ImagingService::class);
        $cat = EquipmentCategory::create(['code' => 'X', 'name' => 'X']);
        $eq = app(\Modules\Inventory\Services\EquipmentService::class)->register(['name' => 'X-ray', 'equipment_category_id' => $cat->id, 'serial_number' => 'SN', 'branch_id' => $this->hq->id], $this->admin->id);
        $eq->update(['status' => 'out_of_service']);
        $this->req->update(['status' => 'approved']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->schedule($this->req->fresh(), $eq->id, '2026-11-10', $this->admin->id);
    }
    public function test_procedure_to_study()
    {
        $svc = app(ImagingService::class);
        $this->req->update(['status' => 'approved']);
        $p = $svc->schedule($this->req->fresh(), null, '2026-11-10', $this->admin->id);
        $study = $svc->acquireStudy($p);
        $this->assertEquals('acquired', $study->status);
        $this->assertEquals('completed', $p->fresh()->status);
    }
    public function test_report_lifecycle()
    {
        $svc = app(ImagingService::class);
        $this->req->update(['status' => 'approved']);
        $p = $svc->schedule($this->req->fresh(), null, '2026-11-10', $this->admin->id);
        $svc->acquireStudy($p);
        $rep = $svc->draftReport($this->req->fresh(), ['findings' => 'Clear', 'impression' => 'Normal'], $this->radiologist->id);
        $this->assertEquals('draft', $rep->status);
        $svc->authorizeReport($rep, $this->reviewer->id);
        $this->assertEquals('authorized', $rep->fresh()->status);
    }
    public function test_self_report_rejected()
    {
        $svc = app(ImagingService::class);
        $this->req->update(['status' => 'approved']);
        $p = $svc->schedule($this->req->fresh(), null, '2026-11-10', $this->admin->id);
        $svc->acquireStudy($p);
        $rep = $svc->draftReport($this->req->fresh(), ['findings' => 'x'], $this->radiologist->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->authorizeReport($rep, $this->radiologist->id);
    }
}
