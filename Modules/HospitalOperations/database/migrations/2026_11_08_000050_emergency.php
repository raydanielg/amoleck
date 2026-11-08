<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_acuity_categories', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('label'); $t->unsignedTinyInteger('priority'); $t->unsignedInteger('target_minutes')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('emergency_visits', function (Blueprint $t) {
            $t->id(); $t->string('visit_number')->unique(); $t->unsignedBigInteger('patient_id')->nullable()->index(); $t->string('temp_patient_number')->nullable(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->string('arrival_mode')->default('walk_in'); $t->string('presenting_complaint')->nullable(); $t->string('status')->default('arrived')->index(); $t->foreignId('registered_by')->constrained('users'); $t->timestamps();
            $t->unique(['temp_patient_number']);
        });
        Schema::create('emergency_triage_assessments', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->foreignId('emergency_acuity_category_id')->constrained()->cascadeOnDelete(); $t->text('complaint')->nullable(); $t->text('notes')->nullable(); $t->foreignId('assessed_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('emergency_alerts', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->string('type'); $t->string('status')->default('active')->index(); $t->foreignId('raised_by')->constrained('users'); $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('acknowledged_at')->nullable(); $t->timestamps();
        });
        Schema::create('emergency_handovers', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->foreignId('from_user_id')->constrained('users'); $t->foreignId('to_user_id')->constrained('users'); $t->text('notes')->nullable(); $t->string('status')->default('pending')->index(); $t->timestamp('acknowledged_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['emergency_handovers','emergency_alerts','emergency_triage_assessments','emergency_visits','emergency_acuity_categories'] as $t) { Schema::dropIfExists($t); }
    }
};
