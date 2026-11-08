<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->unsignedSmallInteger('grace_minutes')->default(0);
            $t->unsignedSmallInteger('break_minutes')->default(0);
            $t->boolean('overnight')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->date('work_date')->index();
            $t->foreignId('assigned_by')->constrained('users');
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
            $t->index(['branch_id', 'work_date']);
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shift_assignment_id')->nullable()->constrained()->nullOnDelete();
            $t->date('work_date')->index();
            $t->timestamp('clock_in')->nullable();
            $t->timestamp('clock_out')->nullable();
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('early_minutes')->default(0);
            $t->unsignedSmallInteger('worked_minutes')->nullable();
            $t->string('status')->default('present')->index();
            $t->string('method')->default('web');
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });

        Schema::create('attendance_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('field');
            $t->string('old_value')->nullable();
            $t->string('new_value');
            $t->string('reason');
            $t->string('status')->default('pending')->index();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['attendance_corrections','attendance_records','shift_assignments','work_shifts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
