<?php

namespace Tests\Feature\PatientCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Clinical\Models\ClinicalExercise;
use Modules\PatientCare\Clinical\Models\ClinicalHomeExerciseProgram;
use Modules\PatientCare\Clinical\Services\ExerciseProgramService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private User $physio; private Branch $hq; private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('PatientCare')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'patientcare')->update(['status' => 'active']);
        $this->physio = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->physio->assignRole('Physiotherapist');
        $this->physio->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->patient = Patient::create(['mrn' => 'PAT-2026-000001', 'first_name' => 'E', 'last_name' => 'P', 'primary_branch_id' => $this->hq->id]);
    }

    private function program(): ClinicalHomeExerciseProgram
    {
        $this->actingAs($this->physio)->post(route('admin.patients.home-programs.store', $this->patient), ['branch_id' => $this->hq->id]);
        return ClinicalHomeExerciseProgram::latest('id')->first();
    }

    public function test_library_create()
    {
        $this->actingAs($this->admin)->post(route('admin.exercises.store'), ['name' => 'Knee Flexion', 'category' => 'range_of_motion', 'instructions_en' => 'Bend', 'instructions_sw' => 'Pinda']);
        $this->assertEquals(1, ClinicalExercise::count());
        $this->assertMatchesRegularExpression('/^EX-\d{5}$/', ClinicalExercise::first()->code);
        $this->actingAs($this->physio)->post(route('admin.exercises.store'), ['name' => 'X', 'category' => 'other'])->assertForbidden();
    }

    public function test_program_created_with_hep_number()
    {
        $p = $this->program();
        $this->assertMatchesRegularExpression('/^HEP-\d{4}-\d{6}$/', $p->hep_number);
        $this->assertEquals('draft', $p->status);
    }

    public function test_program_exercises_and_adherence()
    {
        $p = $this->program();
        $p->update(['status' => 'active']);
        $this->actingAs($this->physio)->put(route('admin.exercise-programs.update', $p->id), ['kind' => 'hep', 'exercises' => [['custom_name' => 'Walk 10m', 'sets' => 3, 'reps' => 10, 'frequency' => 'daily']]]);
        $this->assertEquals(1, $p->exercises()->count());
        $this->actingAs($this->physio)->post(route('admin.exercise-programs.adherence', $p->id), ['performed_on' => now()->toDateString(), 'status' => 'completed', 'source' => 'patient_reported', 'completed_sets' => 3]);
        $this->actingAs($this->physio)->post(route('admin.exercise-programs.adherence', $p->id), ['performed_on' => now()->toDateString(), 'status' => 'skipped', 'source' => 'patient_reported', 'non_completion_reason' => 'Pain']);
        $this->assertEquals(2, $p->adherence()->count());
        $rate = app(ExerciseProgramService::class)->adherenceRate($p->load('adherence'));
        $this->assertEquals(50, $rate['rate']);
    }

    public function test_transition_and_versioning()
    {
        $p = $this->program();
        $this->actingAs($this->physio)->post(route('admin.exercise-programs.transition', $p->id), ['kind' => 'hep', 'status' => 'pending_review']);
        $this->actingAs($this->admin)->post(route('admin.exercise-programs.transition', $p->id), ['kind' => 'hep', 'status' => 'approved']);
        $this->actingAs($this->admin)->post(route('admin.exercise-programs.transition', $p->id), ['kind' => 'hep', 'status' => 'active']);
        $p = $p->fresh();
        $this->assertEquals('active', $p->status);
        $this->assertEquals(3, \Modules\PatientCare\Clinical\Models\ClinicalExerciseVersion::where('entity_id', $p->id)->count());
    }

    public function test_discontinue_requires_reason()
    {
        $p = $this->program();
        $p->update(['status' => 'active']);
        $this->actingAs($this->admin)->post(route('admin.exercise-programs.transition', $p->id), ['kind' => 'hep', 'status' => 'discontinued']);
        $this->assertEquals('active', $p->fresh()->status);
    }

    public function test_cross_branch_denied()
    {
        $other = Branch::create(['name' => 'O', 'code' => 'OB']);
        $foreign = Patient::create(['mrn' => 'PAT-2026-000002', 'first_name' => 'F', 'last_name' => 'X', 'primary_branch_id' => $other->id]);
        $p = ClinicalHomeExerciseProgram::create(['hep_number' => 'HEP-2026-000090', 'patient_id' => $foreign->id, 'branch_id' => $other->id, 'issued_on' => now(), 'created_by' => $this->admin->id]);
        $this->actingAs($this->physio)->get(route('admin.exercise-programs.show', ['program' => $p->id, 'kind' => 'hep']))->assertForbidden();
    }

    public function test_print_hep()
    {
        $p = $this->program();
        $this->actingAs($this->admin)->get(route('admin.exercise-programs.print', $p))->assertOk();
    }

    public function test_unauthorized_cannot_approve()
    {
        $p = $this->program();
        $p->update(['status' => 'pending_review']);
        $staff = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staff->assignRole('Receptionist');
        $staff->branches()->sync([$this->hq->id => ['is_primary' => true]]);
        $this->actingAs($staff)->post(route('admin.exercise-programs.transition', $p->id), ['kind' => 'hep', 'status' => 'approved'])->assertForbidden();
    }
}
