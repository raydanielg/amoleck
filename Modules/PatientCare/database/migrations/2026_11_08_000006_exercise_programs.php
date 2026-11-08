<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_exercises', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->string('category')->index();
            $t->text('description')->nullable();
            $t->text('clinical_purpose')->nullable();
            $t->text('instructions_en')->nullable();
            $t->text('instructions_sw')->nullable();
            $t->string('applicable_regions')->nullable();
            $t->string('difficulty')->default('beginner');
            $t->string('equipment')->nullable();
            $t->text('contraindications')->nullable();
            $t->text('precautions')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('clinical_exercise_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_exercise_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('step_number')->nullable();
            $t->string('instruction')->nullable();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_exercise_prescriptions', function (Blueprint $t) {
            $t->id();
            $t->string('rx_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_treatment_goal_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('prescribed_on');
            $t->date('start_on')->nullable();
            $t->date('review_on')->nullable();
            $t->date('end_on')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('instructions')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('clinical_prescribed_exercises', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_exercise_prescription_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_home_exercise_program_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_exercise_id')->nullable()->constrained()->nullOnDelete();
            $t->string('custom_name')->nullable();
            $t->unsignedSmallInteger('sets')->nullable();
            $t->unsignedSmallInteger('reps')->nullable();
            $t->string('hold_duration')->nullable();
            $t->string('rest_duration')->nullable();
            $t->string('resistance')->nullable();
            $t->string('frequency')->nullable();
            $t->string('days_per_week')->nullable();
            $t->string('program_duration')->nullable();
            $t->text('patient_instructions')->nullable();
            $t->text('precautions')->nullable();
            $t->timestamps();
        });

        Schema::create('clinical_home_exercise_programs', function (Blueprint $t) {
            $t->id();
            $t->string('hep_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_treatment_plan_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_exercise_prescription_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('physiotherapist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('issued_on');
            $t->date('start_on')->nullable();
            $t->date('review_on')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('general_instructions')->nullable();
            $t->text('emergency_instructions')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('clinical_exercise_adherence_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_home_exercise_program_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_prescribed_exercise_id')->nullable()->constrained()->nullOnDelete();
            $t->date('performed_on');
            $t->string('status')->index();
            $t->unsignedSmallInteger('completed_sets')->nullable();
            $t->unsignedSmallInteger('completed_reps')->nullable();
            $t->string('actual_duration')->nullable();
            $t->string('difficulty')->nullable();
            $t->string('pain')->nullable();
            $t->string('non_completion_reason')->nullable();
            $t->text('comments')->nullable();
            $t->string('source')->default('patient_reported');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('clinical_exercise_versions', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['clinical_exercise_versions','clinical_exercise_adherence_records','clinical_home_exercise_programs','clinical_prescribed_exercises','clinical_exercise_prescriptions','clinical_exercise_media','clinical_exercises'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
