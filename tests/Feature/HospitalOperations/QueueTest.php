<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\Appointment;
use Modules\HospitalOperations\Models\PatientVisit;
use Modules\HospitalOperations\Models\QueueEntry;
use Modules\HospitalOperations\Models\QueueType;
use Modules\HospitalOperations\Services\QueueService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class QueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $rec; private Branch $hq; private Branch $b2;
    private Patient $patient; private QueueType $qt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        $this->b2 = Branch::create(['name' => 'B2', 'code' => 'B2']);
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['hospitaloperations', 'patientcare'])->update(['status' => 'active']);
        $this->rec = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->rec->assignRole('Receptionist');
        $this->rec->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Q', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
        $this->qt = QueueType::create(['code' => 'GEN', 'name' => 'General', 'branch_id' => $this->hq->id, 'active' => true]);
    }

    private function visit(array $over = []): PatientVisit
    {
        return app(QueueService::class)->createVisit(array_merge([
            'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id,
            'queue_type_id' => $this->qt->id, 'source' => 'walk_in',
        ], $over), $this->rec->id);
    }

    public function test_visit_created_with_numbers_and_entry()
    {
        $v = $this->visit();
        $this->assertMatchesRegularExpression('/^VIS-\d{4}-\d{6}$/', $v->visit_number);
        $e = $v->queueEntries()->first();
        $this->assertMatchesRegularExpression('/^QT-\d{4}-\d{4}$/', $e->ticket_number);
        $this->assertEquals('waiting', $e->status);
        $this->assertEquals('arrived', $v->status);
    }

    public function test_appointment_linked_visit()
    {
        $a = Appointment::create(['appointment_number' => 'APT-2026-000001', 'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'appointment_date' => today(), 'start_time' => '09:00', 'end_time' => '09:30', 'status' => 'confirmed', 'created_by' => $this->rec->id]);
        $v = $this->visit(['source' => 'appointment', 'appointment_id' => $a->id]);
        $this->assertEquals($a->id, $v->appointment_id);
        // duplicate active visit on same appointment rejected
        $this->actingAs($this->rec)->post(route('admin.queue.visits.store'), ['patient_id' => $this->patient->id, 'branch_id' => $this->hq->id, 'queue_type_id' => $this->qt->id, 'source' => 'appointment', 'appointment_id' => $a->id]);
        $this->assertEquals(1, PatientVisit::where('appointment_id', $a->id)->count());
    }

    public function test_visit_workflow_timestamps()
    {
        $v = $this->visit();
        $svc = app(QueueService::class);
        $svc->transitionVisit($v, 'checked_in', $this->rec->id);
        $this->assertNotNull($v->fresh()->checked_in_at);
        $svc->transitionVisit($v, 'waiting', $this->rec->id);
        $svc->transitionVisit($v, 'called', $this->rec->id);
        $svc->transitionVisit($v, 'in_service', $this->rec->id);
        $this->assertNotNull($v->fresh()->service_started_at);
        $svc->transitionVisit($v, 'completed', $this->rec->id);
        $this->assertEquals('completed', $v->fresh()->status);
        $this->assertNotNull($v->fresh()->completed_at);
        // entry mirrors: completed
        $this->assertEquals('completed', $v->queueEntries()->first()->status);
    }

    public function test_priority_ordering_call_next()
    {
        $normal = $this->visit(['priority' => 'normal']);
        $emergency = $this->visit(['priority' => 'emergency']);
        $called = app(QueueService::class)->callNext($this->qt->id, $this->rec->id);
        $this->assertEquals($emergency->activeEntry()->id, $called->id); // emergency first despite later arrival
    }

    public function test_claim_safe_call()
    {
        $v = $this->visit();
        $e = $v->activeEntry();
        $svc = app(QueueService::class);
        $this->assertTrue($svc->call($e, $this->rec->id));
        // second call on same entry — already 'called' → allowed (recall) but not 'waiting'
        $this->assertTrue($svc->call($e->fresh(), $this->admin->id)); // recall ok
        $e->update(['status' => 'in_service']);
        $this->assertFalse($svc->call($e->fresh(), $this->admin->id)); // can't claim in-service
    }

    public function test_skip_and_requeue()
    {
        $e = $this->visit()->activeEntry();
        $svc = app(QueueService::class);
        $svc->skip($e, $this->rec->id, 'No answer');
        $this->assertEquals('skipped', $e->fresh()->status);
        $svc->requeue($e->fresh(), $this->rec->id);
        $this->assertEquals('waiting', $e->fresh()->status);
        $this->assertEquals(3, $e->statusHistories()->count());
    }

    public function test_transfer_preserves_history()
    {
        $qt2 = QueueType::create(['code' => 'PHY', 'name' => 'Physio', 'branch_id' => $this->hq->id]);
        $e = $this->visit()->activeEntry();
        app(QueueService::class)->transfer($e, $qt2->id, $this->admin->id, 'Wrong dept');
        $e = $e->fresh();
        $this->assertEquals($qt2->id, $e->queue_type_id);
        $this->assertEquals($this->qt->id, $e->transfers()->first()->from_queue_type_id);
    }

    public function test_priority_change_audited()
    {
        $e = $this->visit()->activeEntry();
        app(QueueService::class)->setPriority($e, 'urgent', $this->admin->id, 'Clinical');
        $this->assertEquals('urgent', $e->fresh()->priority);
        $this->assertEquals('queue.priority_changed', AuditLog::latest('id')->first()->action);
    }

    public function test_cancel_requires_reason()
    {
        $v = $this->visit();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(QueueService::class)->transitionVisit($v, 'cancelled', $this->rec->id);
    }

    public function test_cross_branch_denied()
    {
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $this->b2->id]);
        $v = PatientVisit::create(['visit_number' => 'VIS-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $this->b2->id, 'source' => 'walk_in', 'status' => 'arrived', 'created_by' => $this->admin->id]);
        $this->actingAs($this->rec)->get(route('admin.queue.show', $v))->assertForbidden();
    }

    public function test_analytics_uses_real_timestamps()
    {
        $v = $this->visit();
        $e = $v->activeEntry();
        \Illuminate\Support\Facades\DB::table('queue_entries')->where('id', $e->id)->update(['created_at' => now()->subMinutes(20), 'called_at' => now()->subMinutes(5)]);
        $v->update(['service_started_at' => now()->subMinutes(5), 'completed_at' => now()]);
        $a = app(QueueService::class)->analytics($this->qt->id);
        $this->assertEquals(15, $a['avg_wait_min']);
        $this->assertEquals(5, $a['avg_service_min']);
    }

    public function test_display_excludes_patient_details()
    {
        $this->visit();
        $res = $this->actingAs($this->rec)->get(route('admin.queue.display'));
        $res->assertOk();
        $this->assertStringNotContainsString('first_name', $res->getContent());
    }

    public function test_audit_logged()
    {
        $this->visit();
        $this->assertEquals('queue.visit_arrived', AuditLog::latest('id')->first()->action);
    }
}
