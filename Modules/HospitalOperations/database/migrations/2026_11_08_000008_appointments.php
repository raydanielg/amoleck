<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->string('department')->nullable();
            $t->unsignedSmallInteger('default_duration')->default(30);
            $t->text('description')->nullable();
            $t->string('clinician_category')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('clinician_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day_of_week');
            $t->time('start_time');
            $t->time('end_time');
            $t->time('break_start')->nullable();
            $t->time('break_end')->nullable();
            $t->unsignedSmallInteger('slot_duration')->default(30);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['user_id', 'branch_id', 'day_of_week']);
        });

        Schema::create('hospital_resources', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type')->nullable();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('department')->nullable();
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->string('appointment_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_type_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('hospital_resource_id')->nullable()->constrained()->nullOnDelete();
            $t->date('appointment_date');
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedSmallInteger('duration')->nullable();
            $t->string('priority')->default('routine');
            $t->string('status')->default('draft')->index();
            $t->string('reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('checked_in_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['branch_id', 'appointment_date']);
            $t->index(['clinician_id', 'appointment_date']);
        });

        Schema::create('appointment_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('appointment_reschedule_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->date('previous_date');
            $t->time('previous_start');
            $t->time('previous_end');
            $t->date('new_date');
            $t->time('new_start');
            $t->time('new_end');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['appointment_reschedule_histories','appointment_status_histories','appointments','hospital_resources','clinician_schedules','appointment_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
