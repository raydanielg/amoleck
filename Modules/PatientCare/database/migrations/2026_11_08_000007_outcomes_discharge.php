<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_outcome_measures', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->string('category')->index();
            $t->text('description')->nullable();
            $t->text('clinical_purpose')->nullable();
            $t->string('measurement_method')->nullable();
            $t->json('supported_units')->nullable();
            $t->string('scoring_format')->default('numeric');
            $t->text('interpretation')->nullable();
            $t->string('applicable_population')->nullable();
            $t->text('instructions')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('version_no')->default(1);
            $t->timestamps();
        });

        Schema::create('clinical_outcome_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_outcome_measure_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_treatment_session_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->decimal('value_numeric', 10, 2)->nullable();
            $t->string('value_categorical')->nullable();
            $t->string('unit')->nullable();
            $t->string('status')->default('assessed');
            $t->string('not_assessed_reason')->nullable();
            $t->timestamp('assessed_at');
            $t->foreignId('clinician_id')->constrained('users');
            $t->text('observations')->nullable();
            $t->timestamps();
            $t->index(['patient_id', 'clinical_outcome_measure_id', 'assessed_at']);
        });

        Schema::create('clinical_reassessments', function (Blueprint $t) {
            $t->id();
            $t->string('reassessment_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('draft')->index();
            $t->text('subjective')->nullable();
            $t->text('objective')->nullable();
            $t->text('interpretation')->nullable();
            $t->string('recommendation')->nullable();
            $t->text('recommendation_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('clinical_reassessment_goal_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_reassessment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_treatment_goal_id')->constrained()->cascadeOnDelete();
            $t->string('baseline')->nullable();
            $t->string('current_value')->nullable();
            $t->string('target')->nullable();
            $t->string('status')->nullable();
            $t->text('evidence')->nullable();
            $t->text('comments')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_discharges', function (Blueprint $t) {
            $t->id();
            $t->string('discharge_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('discharge_on')->nullable();
            $t->string('type')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('initial_status')->nullable();
            $t->text('final_status')->nullable();
            $t->unsignedTinyInteger('baseline_pain')->nullable();
            $t->unsignedTinyInteger('final_pain')->nullable();
            $t->text('functional_status')->nullable();
            $t->text('goal_achievement')->nullable();
            $t->text('outstanding_concerns')->nullable();
            $t->text('hep_recommendation')->nullable();
            $t->text('follow_up')->nullable();
            $t->text('precautions')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('clinical_discharge_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_discharge_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_reassessment_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_reassessment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['clinical_reassessment_versions','clinical_discharge_versions','clinical_discharges','clinical_reassessment_goal_reviews','clinical_reassessments','clinical_outcome_records','clinical_outcome_measures'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
