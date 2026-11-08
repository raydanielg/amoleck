<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_interventions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->index();
            $t->string('category')->index();
            $t->text('description')->nullable();
            $t->text('clinical_purpose')->nullable();
            $t->string('applicable_regions')->nullable();
            $t->text('contraindications')->nullable();
            $t->text('precautions')->nullable();
            $t->string('equipment')->nullable();
            $t->boolean('active')->default(true)->index();
            $t->timestamps();
        });

        Schema::create('clinical_treatment_plans', function (Blueprint $t) {
            $t->id();
            $t->string('plan_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('clinical_indication')->nullable();
            $t->text('expected_outcomes')->nullable();
            $t->text('precautions')->nullable();
            $t->unsignedSmallInteger('sessions_per_week')->nullable();
            $t->unsignedSmallInteger('expected_sessions')->nullable();
            $t->string('expected_period')->nullable();
            $t->date('review_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('clinical_treatment_plan_problems', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_problem_id')->nullable()->constrained('clinical_problems')->nullOnDelete();
            $t->string('description');
            $t->string('body_region')->nullable();
            $t->string('laterality')->nullable();
            $t->text('supporting_findings')->nullable();
            $t->string('functional_impact')->nullable();
            $t->string('baseline')->nullable();
            $t->string('priority')->default('medium');
            $t->string('target_outcome')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('clinical_treatment_goals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_treatment_plan_problem_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('short_term');
            $t->string('description');
            $t->string('baseline')->nullable();
            $t->string('target')->nullable();
            $t->string('unit')->nullable();
            $t->date('target_date')->nullable();
            $t->string('priority')->default('medium');
            $t->string('measurement_method')->nullable();
            $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('not_started')->index();
            $t->timestamps();
        });

        Schema::create('clinical_goal_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_goal_id')->constrained()->cascadeOnDelete();
            $t->string('value')->nullable();
            $t->string('status')->nullable();
            $t->text('comment')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_planned_interventions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_intervention_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_treatment_plan_problem_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_treatment_goal_id')->nullable()->constrained()->nullOnDelete();
            $t->string('body_region')->nullable();
            $t->string('laterality')->nullable();
            $t->string('frequency')->nullable();
            $t->string('duration')->nullable();
            $t->string('intensity')->nullable();
            $t->unsignedSmallInteger('sets')->nullable();
            $t->unsignedSmallInteger('reps')->nullable();
            $t->string('hold_duration')->nullable();
            $t->string('rest_period')->nullable();
            $t->string('resistance')->nullable();
            $t->string('equipment')->nullable();
            $t->text('precautions')->nullable();
            $t->string('expected_response')->nullable();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_treatment_plan_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users');
            $t->string('decision');
            $t->text('comments')->nullable();
            $t->string('rejection_reason')->nullable();
            $t->unsignedInteger('version')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_treatment_plan_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['clinical_treatment_plan_id', 'version']);
        });

        Schema::create('clinical_treatment_precautions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_plan_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->text('description');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['clinical_treatment_precautions','clinical_treatment_plan_versions','clinical_treatment_plan_reviews','clinical_planned_interventions','clinical_goal_progress','clinical_treatment_goals','clinical_treatment_plan_problems','clinical_treatment_plans','clinical_interventions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
