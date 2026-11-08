<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $t) {
            $t->id();
            $t->string('encounter_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('initial_assessment')->index();
            $t->string('status')->default('draft')->index();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('referring_professional')->nullable();
            $t->dateTime('encounter_at');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->unique()->constrained()->cascadeOnDelete();
            $t->text('mechanism')->nullable();
            $t->string('onset_type')->nullable();
            $t->text('progression')->nullable();
            $t->text('previous_interventions')->nullable();
            $t->text('investigations')->nullable();
            $t->text('previous_episodes')->nullable();
            $t->text('patient_expectations')->nullable();
            $t->text('subjective_summary')->nullable();
            $t->text('objective_summary')->nullable();
            $t->text('functional_impairments')->nullable();
            $t->text('interpretation')->nullable();
            $t->text('identified_problems_summary')->nullable();
            $t->text('limitations')->nullable();
            $t->text('recommendations')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_complaints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('complaint');
            $t->date('onset_date')->nullable();
            $t->string('duration')->nullable();
            $t->string('location')->nullable();
            $t->text('patient_description')->nullable();
            $t->text('aggravating')->nullable();
            $t->text('relieving')->nullable();
            $t->text('previous_treatment')->nullable();
            $t->text('functional_limitations')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_pain_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('location')->nullable();
            $t->string('pain_type')->nullable();
            $t->string('duration')->nullable();
            $t->string('frequency')->nullable();
            $t->unsignedTinyInteger('nprs_current')->nullable();
            $t->unsignedTinyInteger('nprs_best')->nullable();
            $t->unsignedTinyInteger('nprs_worst')->nullable();
            $t->text('aggravating')->nullable();
            $t->text('relieving')->nullable();
            $t->boolean('not_assessable')->default(false);
            $t->string('not_assessable_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_functional_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('activity');
            $t->string('level');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_objective_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('category');
            $t->text('finding');
            $t->timestamps();
        });

        Schema::create('clinical_rom_measurements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('body_region');
            $t->string('joint');
            $t->string('movement');
            $t->string('side')->nullable();
            $t->decimal('active_rom', 6, 2)->nullable();
            $t->decimal('passive_rom', 6, 2)->nullable();
            $t->string('unit')->default('degrees');
            $t->boolean('pain_during')->default(false);
            $t->text('observations')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_strength_measurements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('muscle_group');
            $t->string('body_region')->nullable();
            $t->string('side')->nullable();
            $t->unsignedTinyInteger('grade');
            $t->string('grade_scale')->default('0-5');
            $t->string('pain_or_limitations')->nullable();
            $t->text('observations')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_special_tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('category')->default('special_test');
            $t->string('test_name');
            $t->string('result')->nullable();
            $t->text('interpretation')->nullable();
            $t->text('observations')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_red_flags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('category');
            $t->text('description');
            $t->string('severity')->default('suspected');
            $t->text('action_taken')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_problems', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->string('body_region')->nullable();
            $t->string('laterality')->nullable();
            $t->string('functional_impact')->nullable();
            $t->string('severity')->nullable();
            $t->text('supporting_findings')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });

        Schema::create('clinical_body_map_annotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->string('view');
            $t->string('region');
            $t->string('side')->nullable();
            $t->string('annotation_type');
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_encounter_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['clinical_encounter_id', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['clinical_encounter_versions','clinical_body_map_annotations','clinical_problems','clinical_red_flags','clinical_special_tests','clinical_strength_measurements','clinical_rom_measurements','clinical_objective_findings','clinical_functional_entries','clinical_pain_assessments','clinical_complaints','clinical_assessments','clinical_encounters'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
