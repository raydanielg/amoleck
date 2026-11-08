<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_theatres', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->default('general'); // general|minor|emergency|orthopedic|obstetric|day_surgery|other
            $t->string('location')->nullable();
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->json('equipment_ids')->nullable(); // references to equipment register
            $t->string('status')->default('available')->index(); // available|reserved|occupied|cleaning|maintenance|inspection_required|out_of_service|inactive
            $t->unsignedSmallInteger('prep_minutes')->default(15);
            $t->unsignedSmallInteger('cleaning_minutes')->default(20);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('operating_theatre_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operating_theatre_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('theatre_operating_hours', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operating_theatre_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day_of_week'); // 0=Sun
            $t->time('start_time');
            $t->time('end_time');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['operating_theatre_id', 'day_of_week']);
        });

        Schema::create('theatre_schedule_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operating_theatre_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->boolean('available')->default(false);
            $t->string('reason');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['operating_theatre_id', 'date']);
        });

        Schema::create('surgical_bookings', function (Blueprint $t) {
            $t->id();
            $t->string('booking_number')->unique();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('operating_theatre_id')->constrained()->cascadeOnDelete();
            $t->date('scheduled_date')->index();
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedSmallInteger('prep_minutes')->default(0);
            $t->unsignedSmallInteger('cleaning_minutes')->default(0);
            $t->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assistant_surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('anesthetist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('priority')->default('elective');
            $t->boolean('emergency_override')->default(false);
            $t->string('status')->default('scheduled')->index(); // scheduled|in_progress|completed|cancelled|postponed
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['operating_theatre_id', 'scheduled_date', 'status']);
            $t->index(['surgeon_id', 'scheduled_date']);
        });

        Schema::create('surgical_booking_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_booking_id')->constrained()->cascadeOnDelete();
            $t->string('action'); // created|rescheduled|cancelled|postponed|theatre_changed|started|completed
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_resource_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_booking_id')->constrained()->cascadeOnDelete();
            $t->string('reservable_type'); // theatre|staff|equipment
            $t->unsignedBigInteger('reservable_id');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('status')->default('active')->index(); // active|released|consumed
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['reservable_type', 'reservable_id', 'status']);
            $t->index(['starts_at', 'ends_at']);
        });

        Schema::create('surgical_team_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->string('role'); // lead_surgeon|assistant_surgeon|anesthetist|anesthesia_assistant|scrub_nurse|circulating_nurse|theatre_nurse|recovery_nurse|other
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('assigned')->index(); // assigned|confirmed|replaced|removed
            $t->timestamp('confirmed_at')->nullable();
            $t->foreignId('replaced_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_by')->constrained('users');
            $t->timestamps();
            $t->index(['user_id', 'status']);
            $t->index(['surgical_case_id', 'role', 'status']);
        });

        Schema::create('surgical_team_assignment_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_team_assignment_id')->constrained()->cascadeOnDelete();
            $t->string('action'); // assigned|confirmed|replaced|removed
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'surgical_team_assignment_histories', 'surgical_team_assignments', 'surgical_resource_reservations',
            'surgical_booking_histories', 'surgical_bookings', 'theatre_schedule_exceptions',
            'theatre_operating_hours', 'operating_theatre_status_histories', 'operating_theatres',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
