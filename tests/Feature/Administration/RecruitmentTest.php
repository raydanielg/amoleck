<?php

namespace Tests\Feature\Administration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Administration\Models\JobVacancy;
use Modules\Administration\Models\JobCandidate;
use Modules\Administration\Models\TrainingCourse;
use Modules\Administration\Models\Employee;
use Modules\Administration\Services\HrService;
use Modules\Administration\Services\RecruitmentService;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin; private Branch $hq; private JobVacancy $vacancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@amoleck.test')->firstOrFail();
        $this->hq = Branch::where('code', 'HQ')->first();
        \Nwidart\Modules\Facades\Module::find('Administration')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::where('identifier', 'administration')->update(['status' => 'active']);
        $this->vacancy = app(RecruitmentService::class)->createVacancy(['title' => 'Senior Physiotherapist', 'branch_id' => $this->hq->id, 'openings' => 2, 'status' => 'open'], $this->admin->id);
    }

    public function test_vacancy_numbered()
    {
        $this->assertMatchesRegularExpression('/^VAC-\d{4}-\d{6}$/', $this->vacancy->vacancy_number);
    }

    public function test_candidate_application()
    {
        $c = app(RecruitmentService::class)->apply($this->vacancy, ['first_name' => 'Jane', 'last_name' => 'X', 'email' => 'j@x.com']);
        $this->assertMatchesRegularExpression('/^CND-\d{4}-\d{6}$/', $c->candidate_number);
        $this->assertEquals('applied', $c->status);
    }

    public function test_closed_vacancy_rejects()
    {
        $this->vacancy->update(['status' => 'closed']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(RecruitmentService::class)->apply($this->vacancy, ['first_name' => 'J', 'last_name' => 'X', 'email' => 'j2@x.com']);
    }

    public function test_candidate_workflow()
    {
        $svc = app(RecruitmentService::class);
        $c = $svc->apply($this->vacancy, ['first_name' => 'J', 'last_name' => 'X', 'email' => 'j@x.com']);
        $svc->transitionCandidate($c, 'screening', $this->admin->id);
        $svc->transitionCandidate($c->fresh(), 'interview', $this->admin->id);
        $svc->transitionCandidate($c->fresh(), 'offered', $this->admin->id);
        $this->assertEquals('offered', $c->fresh()->status);
    }

    public function test_invalid_transition_rejected()
    {
        $c = app(RecruitmentService::class)->apply($this->vacancy, ['first_name' => 'J', 'last_name' => 'X', 'email' => 'j@x.com']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(RecruitmentService::class)->transitionCandidate($c, 'hired', $this->admin->id); // applied→hired skips
    }

    public function test_hire_creates_employee()
    {
        $svc = app(RecruitmentService::class);
        $c = $svc->apply($this->vacancy, ['first_name' => 'J', 'last_name' => 'X', 'email' => 'j@x.com']);
        $c->update(['status' => 'offered']);
        $e = $svc->hire($c->fresh(), ['category' => 'physiotherapist', 'employment_type' => 'permanent'], $this->admin->id);
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{6}$/', $e->employee_number);
        $this->assertEquals('hired', $c->fresh()->status);
        $this->assertEquals($e->id, $c->fresh()->employee_id);
        $this->assertEquals(1, $this->vacancy->fresh()->openings); // decremented 2→1
    }

    public function test_non_offered_cannot_hire()
    {
        $c = app(RecruitmentService::class)->apply($this->vacancy, ['first_name' => 'J', 'last_name' => 'X', 'email' => 'j@x.com']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(RecruitmentService::class)->hire($c, ['category' => 'x', 'employment_type' => 'p'], $this->admin->id);
    }

    public function test_training_enrollment_unique()
    {
        $e = app(HrService::class)->register(['first_name' => 'T', 'last_name' => 'E', 'category' => 'nurse', 'primary_branch_id' => $this->hq->id, 'employment_type' => 'permanent'], $this->admin->id);
        $course = TrainingCourse::create(['code' => 'CPD-1', 'name' => 'Manual Therapy', 'category' => 'clinical']);
        $svc = app(RecruitmentService::class);
        $en = $svc->enroll($e, $course->id, $this->admin->id);
        $this->assertEquals('enrolled', $en->status);
        $svc->enroll($e, $course->id, $this->admin->id); // duplicate — firstOrCreate
        $this->assertEquals(1, $course->enrollments()->count());
        $svc->completeTraining($en, $this->admin->id);
        $this->assertEquals('completed', $en->fresh()->status);
    }
}
