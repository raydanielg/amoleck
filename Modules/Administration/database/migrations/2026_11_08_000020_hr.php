<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('title');
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('grade')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('employee_number')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // linked Foundation user
            $t->string('first_name');
            $t->string('middle_name')->nullable();
            $t->string('last_name');
            $t->string('gender')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('category')->default('staff');
            $t->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $t->string('job_title')->nullable();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('primary_branch_id')->constrained('branches');
            $t->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('employment_type')->default('permanent');
            $t->date('start_date')->nullable();
            $t->date('probation_end')->nullable();
            $t->string('status')->default('pending')->index();
            $t->text('hr_notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['primary_branch_id', 'status']);
        });

        Schema::create('employment_contracts', function (Blueprint $t) {
            $t->id();
            $t->string('contract_number')->unique();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('permanent');
            $t->string('job_title')->nullable();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('status')->default('draft')->index();
            $t->string('termination_reason')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('employee_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('status')->default('active')->index();
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });

        Schema::create('employee_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->date('effective_date')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['employee_status_histories','employee_assignments','employment_contracts','employees','job_positions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
