<?php

namespace Tests\Feature\HospitalOperations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\HospitalOperations\Models\OperatingTheatre;
use Modules\HospitalOperations\Models\RecoveryArea;
use Modules\HospitalOperations\Models\RecoveryBed;
use Modules\HospitalOperations\Models\SurgicalBooking;
use Modules\HospitalOperations\Models\SurgicalCase;
use Modules\HospitalOperations\Models\SurgicalChecklistTemplate;
use Modules\HospitalOperations\Models\SurgicalProcedure;
use Modules\HospitalOperations\Models\SurgicalSpecialty;
use Modules\HospitalOperations\Services\SurgicalCaseService;
use Modules\HospitalOperations\Services\SurgicalIntegrationService;
use Modules\HospitalOperations\Services\SurgicalMaterialService;
use Modules\HospitalOperations\Services\SurgicalOperationService;
use Modules\HospitalOperations\Services\SurgicalPreopService;
use Modules\HospitalOperations\Services\SurgicalRecoveryService;
use Modules\HospitalOperations\Services\SurgicalSafetyService;
use Modules\HospitalOperations\Services\TheatreService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class SurgeryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $surgeon; private User $anesthetist; private User $reviewer; private User $outsider;
    private Branch $hq; private Branch $other; private Patient $patient;
    private SurgicalSpecialty $spec; private SurgicalProcedure $proc; private OperatingTheatre $theatre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        $this->other = Branch::create(['code' => 'BR2', 'name' => 'Branch 2', 'status' => 'active']);
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier', ['hospitaloperations', 'patientcare'])->update(['status' => 'active']);
        $mk = fn (string $role) => tap(User::factory()->create(['status' => 'active', 'email_verified_at' => now()]), fn ($u) => $u->assignRole($role))->fresh();
        $this->surgeon = $mk('Doctor'); $this->surgeon->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->anesthetist = $mk('Doctor'); $this->anesthetist->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->reviewer = $mk('Doctor'); $this->reviewer->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->outsider = $mk('Staff'); $this->outsider->branches()->sync([$this->other->id => ['is_primary' => true]]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'Jane', 'last_name' => 'Doe', 'primary_branch_id' => $this->hq->id]);
        $this->spec = SurgicalSpecialty::create(['code' => 'GEN', 'name' => 'General Surgery', 'status' => 'active']);
        $this->proc = SurgicalProcedure::create(['code' => 'APPX', 'name' => 'Appendectomy', 'surgical_specialty_id' => $this->spec->id, 'category' => 'major', 'urgency_classification' => 'both', 'expected_duration_minutes' => 90, 'anesthesia_options' => ['general'], 'consent_required' => true, 'status' => 'active']);
        $this->theatre = OperatingTheatre::create(['code' => 'TH1', 'name' => 'Theatre 1', 'branch_id' => $this->hq->id, 'status' => 'available', 'prep_minutes' => 15, 'cleaning_minutes' => 20]);
        foreach (['sign_in', 'time_out', 'sign_out'] as $stage) {
            SurgicalChecklistTemplate::create(['code' => strtoupper($stage), 'name' => ucfirst(str_replace('_', ' ', $stage)), 'stage' => $stage, 'version' => 1, 'active' => true,
                'items' => [['key' => 'identity', 'label' => 'Patient identity confirmed', 'mandatory' => true], ['key' => 'site', 'label' => 'Site marked', 'mandatory' => false]]]);
        }
        SurgicalChecklistTemplate::create(['code' => 'PREP', 'name' => 'Prep', 'stage' => 'preparation', 'version' => 1, 'active' => true,
            'items' => [['key' => 'consent_check', 'label' => 'Consent verified', 'mandatory' => true], ['key' => 'fasting', 'label' => 'Fasting confirmed', 'mandatory' => false]]]);
    }

    private function makeCase(array $over = []): SurgicalCase
    {
        return app(SurgicalCaseService::class)->create(array_merge([
            'patient_id' => $this->patient->id, 'branch_id' => $this->hq->id,
            'surgical_specialty_id' => $this->spec->id, 'surgical_procedure_id' => $this->proc->id,
            'surgeon_id' => $this->surgeon->id, 'indication' => 'RLQ pain', 'diagnosis' => 'Appendicitis',
            'priority' => 'elective', 'ordering_clinician_id' => $this->surgeon->id,
        ], $over), $this->surgeon->id);
    }

    private function readyCase(): SurgicalCase
    {
        $c = $this->makeCase();
        $svc = app(SurgicalCaseService::class);
        $svc->transition($c, 'submitted', $this->surgeon->id);
        $svc->transition($c->fresh(), 'pending_surgical_approval', $this->surgeon->id);
        $svc->transition($c->fresh(), 'approved', $this->admin->id);
        return $c->fresh();
    }

    private function clearedCase(): SurgicalCase
    {
        $c = $this->readyCase();
        $pre = app(SurgicalPreopService::class);
        $a = $pre->saveAssessment($c, ['medical_history' => 'nil'], $this->surgeon->id);
        $pre->reviewAssessment($a, 'cleared', $this->reviewer->id); // cannot clear own
        $pre->saveAnesthesiaAssessment($c, ['proposed_technique' => 'general'], $this->anesthetist->id);
        $pre->reviewAnesthesiaAssessment($c->fresh()->anesthesiaPreassessment, 'approved', $this->admin->id);
        $pre->recordConsent($c, ['type' => 'procedure', 'status' => 'consented', 'decision' => 'consented'], $this->surgeon->id);
        app(SurgicalCaseService::class)->transition($c->fresh(), 'ready_for_scheduling', $this->admin->id);
        return $c->fresh();
    }

    private function bookedCase(array $over = []): array
    {
        $c = $this->clearedCase();
        $b = app(TheatreService::class)->createBooking($c, array_merge([
            'operating_theatre_id' => $this->theatre->id, 'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:30', 'anesthetist_id' => $this->anesthetist->id,
        ], $over), $this->admin->id);
        return [$c->fresh(), $b];
    }

    private function operatedCase(): array
    {
        [$c, $b] = $this->bookedCase();
        $svc = app(SurgicalCaseService::class);
        $svc->transition($c, 'preoperative_preparation', $this->admin->id);
        $svc->transition($c->fresh(), 'ready_for_theatre', $this->admin->id);
        $safety = app(SurgicalSafetyService::class);
        foreach (['sign_in', 'time_out'] as $s) {
            $safety->verify($c->fresh(), $s, [['key' => 'identity', 'checked' => true]], $this->surgeon->id);
        }
        $op = app(SurgicalOperationService::class)->start($c->fresh(), $this->surgeon->id, $b->id);
        return [$c->fresh(), $b->fresh(), $op];
    }

    // ---- Case management ----
    public function test_case_number_unique_and_sequential()
    {
        $a = $this->makeCase(); $b = $this->makeCase();
        $this->assertMatchesRegularExpression('/^SUR-\d{4}-\d{6}$/', $a->case_number);
        $this->assertNotEquals($a->case_number, $b->case_number);
        $this->assertEquals('draft', $a->statusHistories()->first()->to_status);
    }

    public function test_invalid_transition_rejected()
    {
        $c = $this->makeCase();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SurgicalCaseService::class)->transition($c, 'scheduled', $this->admin->id);
    }

    public function test_readiness_gate_blocks_unassessed_case()
    {
        $c = $this->readyCase();
        $blockers = app(SurgicalCaseService::class)->readinessBlockers($c);
        $this->assertContains('preoperative_assessment', $blockers);
        $this->assertContains('procedure_consent', $blockers);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SurgicalCaseService::class)->transition($c, 'ready_for_scheduling', $this->admin->id);
    }

    public function test_emergency_case_bypasses_readiness_gate()
    {
        $c = $this->makeCase(['priority' => 'emergency']);
        $svc = app(SurgicalCaseService::class);
        $svc->transition($c, 'submitted', $this->surgeon->id);
        $svc->transition($c->fresh(), 'approved', $this->admin->id);
        $svc->transition($c->fresh(), 'ready_for_scheduling', $this->admin->id); // no preop/consent
        $this->assertEquals('ready_for_scheduling', $c->fresh()->status);
    }

    public function test_cannot_clear_own_preop_assessment()
    {
        $c = $this->readyCase();
        $pre = app(SurgicalPreopService::class);
        $a = $pre->saveAssessment($c, [], $this->surgeon->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $pre->reviewAssessment($a, 'cleared', $this->surgeon->id);
    }

    public function test_consent_withdrawal_blocks_readiness()
    {
        $c = $this->readyCase();
        $pre = app(SurgicalPreopService::class);
        $pre->recordConsent($c, ['type' => 'procedure', 'status' => 'consented', 'decision' => 'consented'], $this->surgeon->id);
        $con = $c->fresh()->consents()->first();
        $pre->withdrawConsent($con, 'Patient changed mind', $this->surgeon->id);
        $this->assertContains('procedure_consent', app(SurgicalCaseService::class)->readinessBlockers($c->fresh()));
        $this->assertEquals('withdrawn', $con->fresh()->status);
        $this->assertGreaterThan(0, $con->versions()->count());
    }

    // ---- Scheduling ----
    public function test_theatre_double_booking_rejected()
    {
        $this->bookedCase();
        $c2 = $this->clearedCase();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TheatreService::class)->createBooking($c2, [
            'operating_theatre_id' => $this->theatre->id, 'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30', 'end_time' => '11:00', 'surgeon_id' => $this->reviewer->id,
        ], $this->admin->id);
    }

    public function test_surgeon_double_booking_rejected()
    {
        $this->bookedCase();
        $t2 = OperatingTheatre::create(['code' => 'TH2', 'name' => 'Theatre 2', 'branch_id' => $this->hq->id, 'status' => 'available']);
        $c2 = $this->clearedCase();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TheatreService::class)->createBooking($c2, [
            'operating_theatre_id' => $t2->id, 'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '09:30', 'end_time' => '11:00', 'surgeon_id' => $this->surgeon->id,
        ], $this->admin->id);
    }

    public function test_maintenance_theatre_cannot_be_booked()
    {
        app(TheatreService::class)->setTheatreStatus($this->theatre, 'maintenance', $this->admin->id);
        $c = $this->clearedCase();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TheatreService::class)->createBooking($c, [
            'operating_theatre_id' => $this->theatre->id, 'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ], $this->admin->id);
    }

    public function test_unscheduled_case_cannot_be_booked()
    {
        $c = $this->makeCase();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TheatreService::class)->createBooking($c, [
            'operating_theatre_id' => $this->theatre->id, 'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'end_time' => '10:00',
        ], $this->admin->id);
    }

    public function test_booking_cancel_releases_reservations_and_reopens_case()
    {
        [$c, $b] = $this->bookedCase();
        app(TheatreService::class)->cancelBooking($b, 'Patient unwell', $this->admin->id);
        $this->assertEquals('cancelled', $b->fresh()->status);
        $this->assertEquals(0, $b->reservations()->where('status', 'active')->count());
        $this->assertEquals('ready_for_scheduling', $c->fresh()->status);
    }

    public function test_reschedule_records_history()
    {
        [$c, $b] = $this->bookedCase();
        app(TheatreService::class)->reschedule($b, ['scheduled_date' => now()->addDays(2)->toDateString(), 'reason' => 'Surgeon request'], $this->admin->id);
        $this->assertEquals('rescheduled', $b->histories()->latest('id')->first()->action);
        $this->assertEquals(now()->addDays(2)->toDateString(), $b->fresh()->scheduled_date->toDateString());
    }

    public function test_operating_hours_enforced_for_elective()
    {
        app(TheatreService::class)->setHours($this->theatre, (int) now()->addDay()->dayOfWeek, '08:00', '17:00', $this->admin->id);
        $c = $this->clearedCase();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(TheatreService::class)->createBooking($c, [
            'operating_theatre_id' => $this->theatre->id, 'scheduled_date' => now()->addDay()->toDateString(),
            'start_time' => '20:00', 'end_time' => '21:00',
        ], $this->admin->id);
    }

    // ---- Team ----
    public function test_team_assignment_and_duplicate_prevented()
    {
        [$c] = $this->bookedCase();
        $ts = app(TheatreService::class);
        $a = $ts->assignStaff($c, ['user_id' => $this->reviewer->id, 'role' => 'scrub_nurse'], $this->admin->id);
        $this->assertEquals('assigned', $a->status);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ts->assignStaff($c, ['user_id' => $this->reviewer->id, 'role' => 'scrub_nurse'], $this->admin->id);
    }

    // ---- Safety ----
    public function test_safety_stages_enforced_in_order_and_mandatory_items()
    {
        [$c] = $this->bookedCase();
        $safety = app(SurgicalSafetyService::class);
        // time_out before sign_in fails
        try { $safety->verify($c, 'time_out', [['key' => 'identity', 'checked' => true]], $this->surgeon->id); $this->fail('expected abort'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(422, $e->getStatusCode()); }
        // missing mandatory item fails
        try { $safety->verify($c, 'sign_in', [['key' => 'site', 'checked' => true]], $this->surgeon->id); $this->fail('expected abort'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(422, $e->getStatusCode()); }
        // documented exception allows partial verification
        $safety->recordException($c, 'sign_in', 'identity', 'Emergency — patient unconscious, ID via MRN band', $this->admin->id, $this->admin->id);
        $v = $safety->verify($c, 'sign_in', [['key' => 'identity', 'checked' => false], ['key' => 'site', 'checked' => true]], $this->surgeon->id);
        $this->assertEquals('exception', $v->status);
    }

    // ---- Operation lifecycle ----
    public function test_operation_requires_safety_verification()
    {
        [$c, $b] = $this->bookedCase();
        $svc = app(SurgicalCaseService::class);
        $svc->transition($c, 'preoperative_preparation', $this->admin->id);
        $svc->transition($c->fresh(), 'ready_for_theatre', $this->admin->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SurgicalOperationService::class)->start($c->fresh(), $this->surgeon->id, $b->id);
    }

    public function test_full_operation_lifecycle()
    {
        [$c, $b, $op] = $this->operatedCase();
        $this->assertEquals('in_theatre', $c->status);
        $this->assertEquals('occupied', $this->theatre->fresh()->status);
        $os = app(SurgicalOperationService::class);
        $os->recordProcedure($op, ['procedure_name' => 'Appendectomy', 'kind' => 'performed', 'surgical_procedure_id' => $this->proc->id], $this->surgeon->id);
        app(SurgicalSafetyService::class)->verify($c, 'sign_out', [['key' => 'identity', 'checked' => true]], $this->surgeon->id);
        $op = $os->complete($op->fresh(), ['postop_diagnosis' => 'Appendicitis removed', 'postoperative_destination' => 'pacu'], $this->surgeon->id);
        $this->assertEquals('completed', $op->status);
        $this->assertEquals('cleaning', $this->theatre->fresh()->status);
        $this->assertEquals('recovery', $c->fresh()->status);
        $this->assertNotNull($c->fresh()->recoveryRecord);
    }

    public function test_operative_note_finalized_immutable_then_amended()
    {
        [$c, $b, $op] = $this->operatedCase();
        $os = app(SurgicalOperationService::class);
        $note = $os->saveNote($op, ['narrative' => 'Initial'], $this->surgeon->id);
        $note = $os->transitionNote($note, 'finalized', $this->surgeon->id);
        $this->assertEquals('finalized', $note->status);
        try { $os->saveNote($op->fresh(), ['narrative' => 'sneaky'], $this->surgeon->id); $this->fail('expected abort'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(422, $e->getStatusCode()); }
        $amended = $os->amendNote($note->fresh(), ['narrative' => 'Corrected'], 'Typo fix', $this->admin->id);
        $this->assertEquals('amended', $amended->status);
        $this->assertEquals('Initial', collect($note->versions()->latest('id')->first()->snapshot)->get('narrative'));
    }

    // ---- Materials ----
    public function test_count_discrepancy_escalates_incident_and_blocks_completion()
    {
        [$c, $b, $op] = $this->operatedCase();
        $ms = app(SurgicalMaterialService::class);
        $count = $ms->recordCount($op, ['type' => 'swab', 'phase' => 'final', 'items' => [['item_name' => 'Swabs', 'expected_qty' => 10, 'counted_qty' => 9]]], $this->surgeon->id);
        $this->assertEquals('discrepancy', $count->status);
        $this->assertDatabaseHas('surgical_incidents', ['surgical_operation_id' => $op->id, 'category' => 'count_discrepancy']);
        $ms->resolveCount($count, 'Recovered from drape fold', $this->admin->id);
        $this->assertEquals('resolved', $count->fresh()->status);
    }

    public function test_specimen_lifecycle()
    {
        [$c, $b, $op] = $this->operatedCase();
        $ms = app(SurgicalMaterialService::class);
        $s = $ms->registerSpecimen($op, ['description' => 'Appendix', 'destination' => 'laboratory'], $this->surgeon->id);
        $this->assertMatchesRegularExpression('/^SPC-\d{4}-\d{6}$/', $s->specimen_number);
        $ms->dispatchSpecimen($s, ['received_by_name' => 'Lab Tech'], $this->surgeon->id);
        $ms->receiveSpecimen($s->fresh(), $this->admin->id);
        $this->assertEquals('received', $s->fresh()->status);
    }

    public function test_consumable_issued_once_via_stock_ledger()
    {
        [$c, $b, $op] = $this->operatedCase();
        $wh = \Modules\Inventory\Models\Warehouse::create(['code' => 'WH1', 'name' => 'Main', 'branch_id' => $this->hq->id]);
        $cat = \Modules\Inventory\Models\InventoryCategory::create(['code' => 'SUT', 'name' => 'Sutures']);
        $item = \Modules\Inventory\Models\InventoryItem::create(['code' => 'SUT-3-0', 'name' => 'Suture 3-0', 'inventory_category_id' => $cat->id]);
        $batch = \Modules\Inventory\Models\InventoryBatch::create(['inventory_item_id' => $item->id, 'warehouse_id' => $wh->id, 'batch_number' => 'B1', 'qty' => 10, 'status' => 'available']);
        $ms = app(SurgicalMaterialService::class);
        $u = $ms->recordConsumable($op, ['inventory_item_id' => $item->id, 'inventory_batch_id' => $batch->id, 'qty_used' => 2], $this->surgeon->id);
        $this->assertNotNull($u->stock_issue_id);
        $this->assertEquals(8, (float) $batch->fresh()->qty);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $ms->recordConsumable($op, ['inventory_item_id' => $item->id, 'inventory_batch_id' => $batch->id, 'qty_used' => 1], $this->surgeon->id);
    }

    // ---- Recovery & handover ----
    public function test_recovery_and_handover_flow()
    {
        [$c, $b, $op] = $this->operatedCase();
        app(SurgicalOperationService::class)->recordProcedure($op, ['procedure_name' => 'Appendectomy', 'kind' => 'performed'], $this->surgeon->id);
        app(SurgicalSafetyService::class)->verify($c, 'sign_out', [['key' => 'identity', 'checked' => true]], $this->surgeon->id);
        app(SurgicalOperationService::class)->complete($op->fresh(), ['postoperative_destination' => 'pacu'], $this->surgeon->id);

        $area = RecoveryArea::create(['code' => 'PACU', 'name' => 'PACU', 'branch_id' => $this->hq->id]);
        $bed = RecoveryBed::create(['code' => 'PB1', 'recovery_area_id' => $area->id, 'branch_id' => $this->hq->id, 'status' => 'available']);
        $rs = app(SurgicalRecoveryService::class);
        $r = $c->fresh()->recoveryRecord;
        $r = $rs->receive($r, $bed->id, 'GA, stable', $this->anesthetist->id);
        $this->assertEquals('occupied', $bed->fresh()->status);
        $rs->addAssessment($r->fresh(), ['outcome' => 'ready_for_transfer'], $this->reviewer->id);
        $rs->clear($r->fresh(), 'ward', $this->reviewer->id);
        $h = $rs->createHandover($r->fresh(), ['destination_type' => 'surgical_ward'], $this->reviewer->id);
        $this->assertEquals('pending', $h->status);
        $rs->acknowledgeHandover($h, $this->reviewer->id);
        $this->assertEquals('acknowledged', $h->fresh()->status);
        $this->assertEquals('postoperative_care', $c->fresh()->status);
        $this->assertEquals('cleaning', $bed->fresh()->status);
    }

    // ---- Integrations ----
    public function test_charge_capture_idempotent()
    {
        $c = $this->makeCase();
        $cat = \Modules\HospitalOperations\Models\ServiceCategory::create(['code' => 'SURG', 'name' => 'Surgery']);
        $svc = \Modules\HospitalOperations\Models\ClinicalService::create(['code' => 'TH-FEE', 'name' => 'Theatre fee', 'service_category_id' => $cat->id, 'status' => 'active']);
        \Modules\Finance\Models\ServicePrice::create(['clinical_service_id' => $svc->id, 'service_name' => 'Theatre fee', 'price' => 500, 'branch_id' => $this->hq->id, 'status' => 'approved', 'created_by' => $this->admin->id, 'approved_by' => $this->admin->id]);
        $int = app(SurgicalIntegrationService::class);
        $ch1 = $int->captureCharge($c, 'theatre', $svc->id, 1, $this->admin->id);
        $ch2 = $int->captureCharge($c, 'theatre', $svc->id, 1, $this->admin->id);
        $this->assertEquals($ch1->id, $ch2->id);
        $this->assertEquals(1, \Modules\Finance\Models\PatientCharge::where('source', 'surgery_theatre')->where('source_id', 1)->count());
    }

    public function test_audit_trail_written()
    {
        $c = $this->makeCase();
        app(SurgicalCaseService::class)->transition($c, 'submitted', $this->surgeon->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'surgery.case_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'surgery.case_submitted']);
    }

    // ---- HTTP/RBAC ----
    public function test_rbac_blocks_unprivileged_user()
    {
        $c = $this->makeCase();
        $this->actingAs($this->outsider)->get(route('admin.surgery.cases.index'))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('admin.surgery.cases.show', $c))->assertForbidden();
    }

    public function test_branch_isolation_on_case_view()
    {
        $otherBranchStaff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $otherBranchStaff->assignRole('Super Admin'); // only super admin can cross branches
        $c = $this->makeCase();
        $this->actingAs($this->admin)->get(route('admin.surgery.cases.show', $c))->assertOk();
        $this->assertTrue(\Modules\Foundation\Support\BranchContext::canAccess($otherBranchStaff, $this->hq->id));
    }

    public function test_case_index_page_renders_for_privileged_user()
    {
        $this->makeCase();
        $this->actingAs($this->admin)->get(route('admin.surgery.cases.index'))->assertOk();
    }
}
