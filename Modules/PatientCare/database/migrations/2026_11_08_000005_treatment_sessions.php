<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_treatment_sessions', function (Blueprint $t) {
            $t->id();
            $t->string('session_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('treating_physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type')->default('routine');
            $t->string('status')->default('draft')->index();
            $t->dateTime('session_at');
            $t->string('pre_condition')->nullable();
            $t->unsignedTinyInteger('pre_pain')->nullable();
            $t->string('pre_functional')->nullable();
            $t->text('pre_changes')->nullable();
            $t->text('pre_medical_updates')->nullable();
            $t->boolean('pre_not_assessed')->default(false);
            $t->string('pre_not_assessed_reason')->nullable();
            $t->unsignedTinyInteger('post_pain')->nullable();
            $t->text('post_functional')->nullable();
            $t->text('post_feedback')->nullable();
            $t->string('overall_tolerance')->nullable();
            $t->text('stop_reason')->nullable();
            $t->text('post_next_steps')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('clinical_session_interventions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_planned_intervention_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_intervention_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('status')->default('performed')->index();
            $t->string('not_performed_reason')->nullable();
            $t->string('technique')->nullable();
            $t->string('dosage')->nullable();
            $t->string('intensity')->nullable();
            $t->string('duration')->nullable();
            $t->unsignedSmallInteger('sets')->nullable();
            $t->unsignedSmallInteger('reps')->nullable();
            $t->string('resistance')->nullable();
            $t->string('equipment')->nullable();
            $t->string('tolerance')->nullable();
            $t->text('observations')->nullable();
            $t->text('modification')->nullable();
            $t->string('modification_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_session_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_session_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('template')->default('soap');
            $t->text('subjective')->nullable();
            $t->text('objective')->nullable();
            $t->text('assessment')->nullable();
            $t->text('plan')->nullable();
            $t->text('narrative')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_session_adverse_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_session_id')->constrained()->cascadeOnDelete();
            $t->text('description');
            $t->text('action_taken')->nullable();
            $t->string('escalation')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_session_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users');
            $t->string('decision');
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_session_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_treatment_session_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['clinical_treatment_session_id', 'version']);
        });

        Schema::table('clinical_goal_progress', function (Blueprint $t) {
            $t->foreignId('clinical_treatment_session_id')->nullable()->after('clinical_treatment_goal_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_goal_progress', fn (Blueprint $t) => $t->dropConstrainedForeignId('clinical_treatment_session_id'));
        foreach (['clinical_session_versions','clinical_session_reviews','clinical_session_adverse_events','clinical_session_notes','clinical_session_interventions','clinical_treatment_sessions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
