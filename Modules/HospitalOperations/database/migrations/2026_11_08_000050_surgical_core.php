<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Surgical catalogue ----
        Schema::create('surgical_specialties', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->text('description')->nullable();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('required_clinician_categories')->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->string('status')->default('active')->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('surgical_specialty_branch', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_specialty_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->boolean('available')->default(true);
            $t->timestamps();
            $t->unique(['surgical_specialty_id', 'branch_id']);
        });

        Schema::create('surgical_procedures', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->foreignId('surgical_specialty_id')->constrained()->cascadeOnDelete();
            $t->text('description')->nullable();
            $t->string('category')->default('elective')->index(); // major|minor|day|emergency|elective|diagnostic|therapeutic|other
            $t->string('urgency_classification')->default('elective'); // elective|emergency|both
            $t->unsignedSmallInteger('expected_duration_minutes')->default(60);
            $t->string('theatre_category')->nullable();
            $t->json('required_team_roles')->nullable();
            $t->json('required_equipment_categories')->nullable();
            $t->json('required_consumables')->nullable();
            $t->json('anesthesia_options')->nullable();
            $t->json('preop_template')->nullable();
            $t->boolean('consent_required')->default(true);
            $t->json('recovery_requirements')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->string('status')->default('draft')->index(); // draft|active|inactive
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('surgical_procedure_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_procedure_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
            $t->index(['surgical_procedure_id', 'version']);
        });

        Schema::create('surgical_procedure_branch', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_procedure_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->boolean('available')->default(true);
            $t->timestamps();
            $t->unique(['surgical_procedure_id', 'branch_id']);
        });

        // ---- Surgical cases ----
        Schema::create('surgical_cases', function (Blueprint $t) {
            $t->id();
            $t->string('case_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('surgical_specialty_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('surgical_procedure_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('procedure_version')->nullable();
            $t->foreignId('ordering_clinician_id')->constrained('users');
            $t->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('referring_clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $t->text('indication')->nullable();
            $t->text('diagnosis')->nullable();
            $t->string('priority')->default('elective')->index(); // emergency|urgent|semi_urgent|elective
            $t->date('proposed_date')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('notes')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['branch_id', 'status']);
            $t->index(['patient_id', 'status']);
        });

        Schema::create('surgical_case_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->string('approval_reference')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_case_diagnoses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('working'); // working|preop|postop
            $t->text('description');
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_consultations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->unique()->constrained()->cascadeOnDelete();
            $t->text('presenting_complaint')->nullable();
            $t->text('clinical_history')->nullable();
            $t->text('previous_treatment')->nullable();
            $t->text('examination_summary')->nullable();
            $t->text('indication')->nullable();
            $t->text('proposed_procedure')->nullable();
            $t->text('alternatives')->nullable();
            $t->text('risks')->nullable();
            $t->text('expected_benefits')->nullable();
            $t->text('recommendation')->nullable();
            $t->string('decision')->default('recommended'); // recommended|accepted|deferred|rejected|further_assessment|referred
            $t->timestamp('decision_at')->nullable();
            $t->foreignId('clinician_id')->constrained('users');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('surgical_consultation_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_consultation_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_case_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_document_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->default('other');
            $t->string('description')->nullable();
            $t->string('path')->nullable();
            $t->string('original_name')->nullable();
            $t->string('mime')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // ---- Pre-operative ----
        Schema::create('preoperative_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->text('medical_history')->nullable();
            $t->text('previous_surgery')->nullable();
            $t->text('current_medications')->nullable();
            $t->text('allergies_summary')->nullable();
            $t->text('anesthesia_history')->nullable();
            $t->text('family_social_history')->nullable();
            $t->text('clinical_condition')->nullable();
            $t->text('examination_findings')->nullable();
            $t->text('functional_status')->nullable();
            $t->text('precautions')->nullable();
            $t->text('investigations_summary')->nullable();
            $t->text('outstanding_investigations')->nullable();
            $t->text('clinical_notes')->nullable();
            $t->text('outstanding_actions')->nullable();
            $t->text('readiness_recommendation')->nullable();
            $t->string('status')->default('in_progress')->index(); // in_progress|pending_review|cleared|cleared_with_conditions|not_cleared|requires_further_assessment
            $t->foreignId('clinician_id')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('assessed_at')->nullable();
            $t->timestamp('cleared_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });

        Schema::create('preoperative_assessment_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('preoperative_assessment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('anesthesia_preassessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('anesthetist_id')->constrained('users');
            $t->text('medical_history')->nullable();
            $t->text('previous_anesthesia')->nullable();
            $t->text('allergies')->nullable();
            $t->text('current_medications')->nullable();
            $t->text('examination')->nullable();
            $t->text('investigations')->nullable();
            $t->text('airway_assessment')->nullable();
            $t->text('considerations')->nullable();
            $t->string('proposed_technique')->nullable(); // general|regional|local|sedation|other
            $t->text('clinical_notes')->nullable();
            $t->string('status')->default('in_progress')->index(); // in_progress|pending_review|approved|not_approved|requires_further_assessment
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });

        Schema::create('anesthesia_preassessment_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('anesthesia_preassessment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('type')->index(); // procedure|anesthesia|blood_product|additional|other
            $t->foreignId('surgical_procedure_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('consented_at')->nullable();
            $t->text('information_explained')->nullable();
            $t->text('risks_alternatives')->nullable();
            $t->string('decision')->nullable(); // consented|declined
            $t->string('representative_name')->nullable();
            $t->string('representative_relationship')->nullable();
            $t->string('witness_name')->nullable();
            $t->string('interpreter_name')->nullable();
            $t->string('document_path')->nullable();
            $t->string('status')->default('pending')->index(); // pending|consented|declined|withdrawn|requires_reconfirmation|not_applicable
            $t->text('status_reason')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('withdrawal_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
            $t->unique(['surgical_case_id', 'type']);
        });

        Schema::create('surgical_consent_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_consent_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        // ---- Configurable checklists (templates are configuration; instances are relational) ----
        Schema::create('surgical_checklist_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('stage')->index(); // preparation|sign_in|time_out|sign_out
            $t->unsignedInteger('version')->default(1);
            $t->json('items'); // [{key,label,mandatory,role}]
            $t->boolean('active')->default(true);
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('effective_from')->nullable();
            $t->timestamps();
        });

        Schema::create('surgical_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_checklist_template_id')->constrained()->cascadeOnDelete();
            $t->string('stage')->index();
            $t->string('item_key');
            $t->string('label');
            $t->boolean('mandatory')->default(false);
            $t->string('required_role')->nullable();
            $t->string('status')->default('pending')->index(); // pending|completed|not_applicable|blocked|requires_review
            $t->text('evidence')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['surgical_case_id', 'surgical_checklist_template_id', 'item_key']);
        });

        // ---- Blood bank boundary (Phase 12 dependency) ----
        Schema::create('surgical_blood_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('product_type');
            $t->unsignedSmallInteger('units')->default(1);
            $t->string('status')->default('requested')->index(); // requested|confirmed|unavailable|cancelled
            $t->text('notes')->nullable();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });

        // ---- Lightweight alert log (no external notification infra exists) ----
        Schema::create('surgical_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('type')->index();
            $t->string('channel')->default('in_app');
            $t->text('message');
            $t->string('status')->default('recorded')->index(); // recorded|dispatched|failed
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'surgical_alerts', 'surgical_blood_requirements', 'surgical_checklist_items', 'surgical_checklist_templates',
            'surgical_consent_versions', 'surgical_consents', 'anesthesia_preassessment_versions', 'anesthesia_preassessments',
            'preoperative_assessment_versions', 'preoperative_assessments', 'surgical_case_documents',
            'surgical_consultation_versions', 'surgical_consultations', 'surgical_case_diagnoses',
            'surgical_case_status_histories', 'surgical_cases', 'surgical_procedure_branch',
            'surgical_procedure_versions', 'surgical_procedures', 'surgical_specialty_branch', 'surgical_specialties',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
