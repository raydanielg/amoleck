<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_areas', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('capacity')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('recovery_beds', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('recovery_area_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('available')->index(); // available|occupied|cleaning|maintenance
            $t->timestamp('cleaning_since')->nullable();
            $t->timestamps();
        });

        Schema::create('recovery_bed_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_recovery_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('recovery_bed_id')->constrained()->cascadeOnDelete();
            $t->timestamp('assigned_at');
            $t->timestamp('released_at')->nullable();
            $t->foreignId('assigned_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_recovery_records', function (Blueprint $t) {
            $t->id();
            $t->string('record_number')->unique();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_operation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('recovery_bed_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('arrived_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('anesthesia_handover')->nullable();
            $t->text('clinical_notes')->nullable();
            $t->string('status')->default('awaiting_recovery')->index(); // awaiting_recovery|received|under_observation|reassessment_required|ready_for_review|cleared_for_transfer|transferred|escalated|cancelled
            $t->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cleared_at')->nullable();
            $t->timestamp('transferred_at')->nullable();
            $t->string('disposition')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['branch_id', 'status']);
        });

        Schema::create('surgical_recovery_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_recovery_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assessed_by')->constrained('users');
            $t->timestamp('assessed_at');
            $t->text('observations')->nullable();
            $t->unsignedTinyInteger('pain_score')->nullable();
            $t->string('consciousness')->nullable();
            $t->text('interventions')->nullable();
            $t->text('adverse_events')->nullable();
            $t->text('notes')->nullable();
            $t->string('outcome')->nullable(); // progressing|reassessment_required|ready_for_transfer|escalated
            $t->string('disposition_recommendation')->nullable();
            $t->timestamps();
        });

        Schema::create('surgical_handovers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_operation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->text('operation_summary')->nullable();
            $t->text('procedure_performed')->nullable();
            $t->text('anesthesia_summary')->nullable();
            $t->text('medications_summary')->nullable();
            $t->text('observations')->nullable();
            $t->text('allergies')->nullable();
            $t->text('complications')->nullable();
            $t->text('outstanding_investigations')->nullable();
            $t->text('precautions')->nullable();
            $t->text('pending_actions')->nullable();
            $t->string('destination_type'); // ward|surgical_ward|icu|hdu|recovery|other
            $t->foreignId('destination_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $t->foreignId('destination_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $t->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sent_by')->constrained('users');
            $t->timestamp('sent_at');
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at')->nullable();
            $t->string('status')->default('pending')->index(); // pending|acknowledged
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'surgical_handovers', 'surgical_recovery_assessments', 'surgical_recovery_records',
            'recovery_bed_assignments', 'recovery_beds', 'recovery_areas',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
