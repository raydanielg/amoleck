<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('icu_units', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->unsignedInteger('capacity')->default(0); $t->string('status')->default('active')->index(); $t->boolean('isolation_capable')->default(false); $t->timestamps();
        });
        Schema::create('icu_beds', function (Blueprint $t) {
            $t->id(); $t->foreignId('icu_unit_id')->constrained()->cascadeOnDelete(); $t->foreignId('bed_id')->nullable()->index(); $t->string('status')->default('available')->index(); $t->boolean('isolation')->default(false); $t->timestamps();
            $t->unique(['bed_id']);
        });
        Schema::create('icu_admission_requests', function (Blueprint $t) {
            $t->id(); $t->string('request_number')->unique(); $t->unsignedBigInteger('patient_id')->index(); $t->foreignId('icu_unit_id')->constrained()->cascadeOnDelete(); $t->string('priority')->default('planned'); $t->string('status')->default('submitted')->index(); $t->foreignId('requested_by')->constrained('users'); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('reject_reason')->nullable(); $t->timestamps();
        });
        Schema::create('icu_stays', function (Blueprint $t) {
            $t->id(); $t->string('stay_number')->unique(); $t->foreignId('icu_admission_request_id')->constrained()->cascadeOnDelete(); $t->foreignId('icu_bed_id')->constrained()->cascadeOnDelete(); $t->string('status')->default('active')->index(); $t->timestamp('admitted_at')->nullable(); $t->timestamp('discharged_at')->nullable(); $t->timestamps();
            $t->unique(['icu_admission_request_id']);
        });
        Schema::create('icu_observations', function (Blueprint $t) {
            $t->id(); $t->foreignId('icu_stay_id')->constrained()->cascadeOnDelete(); $t->string('type')->index(); $t->decimal('value', 14, 4)->nullable(); $t->string('unit')->nullable(); $t->timestamp('measured_at')->nullable(); $t->foreignId('recorded_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('icu_fluid_entries', function (Blueprint $t) {
            $t->id(); $t->foreignId('icu_stay_id')->constrained()->cascadeOnDelete(); $t->string('direction')->index(); $t->string('category')->nullable(); $t->decimal('amount', 10, 2); $t->string('unit')->default('ml'); $t->foreignId('recorded_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('icu_transfers', function (Blueprint $t) {
            $t->id(); $t->string('transfer_number')->unique(); $t->foreignId('icu_stay_id')->constrained()->cascadeOnDelete(); $t->string('destination')->nullable(); $t->string('status')->default('pending')->index(); $t->foreignId('requested_by')->constrained('users'); $t->timestamps();
        });
    }
    public function down(): void { foreach (['icu_transfers','icu_fluid_entries','icu_observations','icu_stays','icu_admission_requests','icu_beds','icu_units'] as $t) { Schema::dropIfExists($t); } }
};
