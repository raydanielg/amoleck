<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->default('general');
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedSmallInteger('capacity')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('category')->default('general');
            $t->unsignedSmallInteger('capacity')->default(1);
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('beds', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('number');
            $t->foreignId('room_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('category')->default('general');
            $t->string('status')->default('available')->index();
            $t->timestamp('cleaning_since')->nullable();
            $t->timestamps();
            $t->index(['ward_id', 'status']);
        });

        Schema::create('admissions', function (Blueprint $t) {
            $t->id();
            $t->string('admission_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type')->default('elective');
            $t->string('source')->default('opd');
            $t->string('priority')->default('routine');
            $t->text('reason')->nullable();
            $t->string('status')->default('draft')->index();
            $t->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('bed_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('admitted_at')->nullable();
            $t->timestamp('discharged_at')->nullable();
            $t->string('discharge_type')->nullable();
            $t->string('discharge_reason')->nullable();
            $t->foreignId('discharge_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['branch_id', 'status']);
        });

        Schema::create('bed_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bed_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $t->foreignId('room_id')->constrained()->cascadeOnDelete();
            $t->timestamp('allocated_at');
            $t->timestamp('released_at')->nullable();
            $t->foreignId('allocated_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('admission_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('inpatient_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $t->foreignId('to_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $t->foreignId('from_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $t->foreignId('to_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->foreignId('requested_by')->constrained('users');
            $t->timestamp('completed_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['inpatient_transfers','admission_status_histories','bed_allocations','admissions','beds','rooms','wards'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
