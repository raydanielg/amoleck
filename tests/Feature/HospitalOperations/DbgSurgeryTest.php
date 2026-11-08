<?php
namespace Tests\Feature\HospitalOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\PatientCare\Models\Patient;
use Tests\TestCase;

class DbgSurgeryTest extends TestCase
{
    use RefreshDatabase;
    public function test_debug_403()
    {
        $this->seed(\Modules\Foundation\Database\Seeders\FoundationDatabaseSeeder::class);
        $admin = User::where('email','admin@amoleck.test')->firstOrFail();
        $hq = Branch::where('code','HQ')->first();
        \Nwidart\Modules\Facades\Module::find('HospitalOperations')?->enable();
        app(ModuleRegistryService::class)->sync();
        SystemModule::whereIn('identifier',['hospitaloperations'])->update(['status'=>'active']);
        $p = Patient::create(['mrn'=>'X','first_name'=>'A','last_name'=>'B','primary_branch_id'=>$hq->id]);
        $spec = \Modules\HospitalOperations\Models\SurgicalSpecialty::create(['code'=>'GEN','name'=>'Gen','status'=>'active']);
        $proc = \Modules\HospitalOperations\Models\SurgicalProcedure::create(['code'=>'APPX','name'=>'Appx','surgical_specialty_id'=>$spec->id,'category'=>'major','urgency_classification'=>'both','status'=>'active']);
        $c = app(\Modules\HospitalOperations\Services\SurgicalCaseService::class)->create([
            'patient_id'=>$p->id,'branch_id'=>$hq->id,'surgical_specialty_id'=>$spec->id,'surgical_procedure_id'=>$proc->id,
            'indication'=>'x','priority'=>'elective','ordering_clinician_id'=>$admin->id], $admin->id);

        // exercise the guard logic exactly as the controller does, but via HTTP sub-request user
        $this->actingAs($admin);
        $reqUser = \Illuminate\Support\Facades\Auth::user();
        fwrite(STDERR, "\nsameUser: ".var_export($reqUser->id === $admin->id, true)."\n");
        fwrite(STDERR, "hasRole: ".var_export($reqUser->hasRole('Super Admin'), true)."\n");
        fwrite(STDERR, "allowed: ".\Modules\Foundation\Support\BranchContext::allowed($reqUser)->pluck('id')->implode(',')."\n");
        fwrite(STDERR, "branchId: ".var_export($c->branch_id, true)." type=".gettype($c->branch_id)."\n");
        $this->assertTrue(true);
    }
}
