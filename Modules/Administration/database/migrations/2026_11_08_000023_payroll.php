<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type')->index(); // earning|deduction|employer
            $t->boolean('taxable')->default(false);
            $t->decimal('rate', 8, 4)->nullable(); // % of basic or fixed
            $t->decimal('fixed_amount', 14, 2)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('employee_salaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->decimal('basic_salary', 14, 2);
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('status')->default('active')->index();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['employee_id', 'effective_from']);
        });

        Schema::create('statutory_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('type')->index(); // employee|employer
            $t->decimal('rate', 8, 4);
            $t->decimal('ceiling', 14, 2)->nullable();
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->string('source')->nullable();
            $t->timestamps();
            $t->index(['code', 'effective_from']);
        });

        Schema::create('employee_loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->decimal('principal', 14, 2);
            $t->decimal('installment', 14, 2);
            $t->decimal('remaining', 14, 2);
            $t->string('status')->default('active')->index();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->date('cutoff_date')->nullable();
            $t->string('status')->default('open')->index();
            $t->timestamps();
            $t->unique(['start_date', 'end_date']);
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->string('run_number')->unique();
            $t->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->decimal('gross', 14, 2)->default(0);
            $t->decimal('deductions', 14, 2)->default(0);
            $t->decimal('net', 14, 2)->default(0);
            $t->decimal('employer_contrib', 14, 2)->default(0);
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
            $t->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payroll_run_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->string('component');
            $t->string('direction')->index(); // earning|deduction|employer
            $t->decimal('amount', 14, 2);
            $t->string('memo')->nullable();
            $t->timestamps();
        });

        Schema::create('payslips', function (Blueprint $t) {
            $t->id();
            $t->string('payslip_number')->unique();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('issued')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payslips','payroll_run_lines','payroll_runs','payroll_periods','employee_loans','statutory_rules','employee_salaries','salary_components'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
