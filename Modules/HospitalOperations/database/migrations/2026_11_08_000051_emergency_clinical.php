<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_encounters', function (Blueprint $t) {
            $t->id(); $t->string('encounter_number')->unique(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete(); $t->string('status')->default('awaiting_clinician')->index(); $t->timestamps();
            $t->unique(['emergency_visit_id']);
        });
        Schema::create('emergency_assessments', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_encounter_id')->constrained()->cascadeOnDelete(); $t->text('complaint')->nullable(); $t->text('history')->nullable(); $t->text('examination')->nullable(); $t->string('status')->default('draft')->index(); $t->foreignId('assessed_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('emergency_diagnoses', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_encounter_id')->constrained()->cascadeOnDelete(); $t->text('working_diagnosis')->nullable(); $t->text('differential')->nullable(); $t->string('status')->default('working')->index(); $t->foreignId('diagnosed_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('emergency_treatment_plans', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_encounter_id')->constrained()->cascadeOnDelete(); $t->text('plan')->nullable(); $t->string('status')->default('draft')->index(); $t->integer('version')->default(1); $t->foreignId('created_by')->constrained('users'); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('emergency_interventions', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_encounter_id')->constrained()->cascadeOnDelete(); $t->string('procedure_type'); $t->text('notes')->nullable(); $t->string('status')->default('planned')->index(); $t->foreignId('performed_by')->constrained('users'); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        Schema::create('emergency_referrals', function (Blueprint $t) {
            $t->id(); $t->string('referral_number')->unique(); $t->foreignId('emergency_encounter_id')->constrained()->cascadeOnDelete(); $t->string('type')->default('internal'); $t->string('destination')->nullable(); $t->text('reason')->nullable(); $t->string('status')->default('pending')->index(); $t->foreignId('referred_by')->constrained('users'); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['emergency_referrals','emergency_interventions','emergency_treatment_plans','emergency_diagnoses','emergency_assessments','emergency_encounters'] as $t) { Schema::dropIfExists($t); }
    }
};
