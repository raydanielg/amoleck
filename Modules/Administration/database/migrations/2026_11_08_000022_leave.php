<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->boolean('paid')->default(true);
            $t->boolean('requires_document')->default(false);
            $t->unsignedSmallInteger('max_days')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('date')->index();
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['date', 'branch_id']);
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->string('request_number')->unique();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedSmallInteger('days');
            $t->string('status')->default('draft')->index();
            $t->text('reason')->nullable();
            $t->string('reject_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['employee_id', 'status']);
        });

        Schema::create('leave_balance_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $t->string('type')->index(); // accrual|reservation|use|release|adjustment|carryforward
            $t->decimal('days', 5, 1);
            $t->string('reference')->nullable();
            $t->string('reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['employee_id', 'leave_type_id']);
        });

        Schema::create('return_to_work_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $t->date('expected_return');
            $t->date('actual_return')->nullable();
            $t->string('status')->default('pending')->index();
            $t->text('notes')->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['return_to_work_records','leave_balance_transactions','leave_requests','public_holidays','leave_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
