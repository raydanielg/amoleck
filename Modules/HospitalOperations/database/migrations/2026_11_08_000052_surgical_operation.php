<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Safety verification ----
        Schema::create('surgical_safety_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_operation_id')->nullable()->constrained()->nullOnDelete();
            $t->string('stage'); // sign_in|time_out|sign_out
            $t->string('status')->default('completed')->index(); // completed|partial|exception
            $t->foreignId('performed_by')->constrained('users');
            $t->timestamp('performed_at');
            $t->json('participant_ids')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['surgical_case_id', 'stage']);
        });

        Schema::create('surgical_safety_verification_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_safety_verification_id')->constrained()->cascadeOnDelete();
            $t->string('item_key');
            $t->string('label');
            $t->boolean('checked')->default(false);
            $t->string('value')->nullable();
            $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('surgical_safety_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->string('stage');
            $t->string('item_key')->nullable();
            $t->text('reason');
            $t->foreignId('authorized_by')->constrained('users');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        // ---- Operations ----
        Schema::create('surgical_operations', function (Blueprint $t) {
            $t->id();
            $t->string('operation_number')->unique();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_booking_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('operating_theatre_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('anesthetist_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('in_progress')->index(); // in_progress|completed|aborted
            $t->timestamp('patient_in_at')->nullable();
            $t->timestamp('anesthesia_start_at')->nullable();
            $t->timestamp('procedure_start_at')->nullable();
            $t->timestamp('procedure_end_at')->nullable();
            $t->timestamp('anesthesia_end_at')->nullable();
            $t->timestamp('patient_out_at')->nullable();
            $t->text('preop_diagnosis')->nullable();
            $t->text('postop_diagnosis')->nullable();
            $t->text('findings')->nullable();
            $t->text('narrative')->nullable();
            $t->text('complications')->nullable();
            $t->unsignedInteger('estimated_blood_loss_ml')->nullable();
            $t->text('unexpected_events')->nullable();
            $t->string('outcome')->nullable();
            $t->string('postoperative_destination')->nullable(); // ward|surgical_ward|icu|hdu|pacu|home|other
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['branch_id', 'status']);
        });

        Schema::create('surgical_operation_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('surgical_operation_procedures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_procedure_id')->nullable()->constrained()->nullOnDelete();
            $t->string('procedure_name');
            $t->string('kind')->default('performed'); // planned|performed|additional|changed|aborted|incomplete
            $t->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('operative_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->text('preop_diagnosis')->nullable();
            $t->text('postop_diagnosis')->nullable();
            $t->text('findings')->nullable();
            $t->text('technique')->nullable();
            $t->text('narrative')->nullable();
            $t->text('instructions')->nullable();
            $t->string('status')->default('draft')->index(); // draft|in_progress|pending_review|finalized|amended
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('author_id')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
            $t->index(['surgical_case_id', 'status']);
        });

        Schema::create('operative_note_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('operative_note_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('anesthesia_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('anesthetist_id')->constrained('users');
            $t->string('technique')->nullable(); // general|regional|local|sedation|other
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->text('observations')->nullable();
            $t->text('airway_management')->nullable();
            $t->text('fluids')->nullable();
            $t->text('adverse_events')->nullable();
            $t->text('interventions')->nullable();
            $t->text('clinical_notes')->nullable();
            $t->text('handover_notes')->nullable();
            $t->string('status')->default('open')->index(); // open|closed|amended
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });

        Schema::create('anesthesia_medication_refs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('anesthesia_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('medication_administration_id')->constrained()->cascadeOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['anesthesia_record_id', 'medication_administration_id']);
        });

        // ---- Counts, specimens, materials ----
        Schema::create('surgical_counts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // instrument|swab|needle|other
            $t->string('phase'); // initial|additional|final
            $t->string('status')->default('matched')->index(); // matched|discrepancy|resolved
            $t->text('discrepancy_notes')->nullable();
            $t->text('resolution')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('counted_by')->constrained('users');
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['surgical_operation_id', 'type', 'phase']);
        });

        Schema::create('surgical_count_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_count_id')->constrained()->cascadeOnDelete();
            $t->string('item_name');
            $t->unsignedInteger('expected_qty')->default(0);
            $t->unsignedInteger('counted_qty')->default(0);
            $t->integer('difference')->default(0);
            $t->timestamps();
        });

        Schema::create('surgical_specimens', function (Blueprint $t) {
            $t->id();
            $t->string('specimen_number')->unique();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->timestamp('collected_at');
            $t->foreignId('collected_by')->constrained('users');
            $t->text('description');
            $t->string('anatomical_source')->nullable();
            $t->string('laterality')->nullable();
            $t->string('container')->nullable();
            $t->string('label')->nullable();
            $t->string('destination')->nullable(); // laboratory|pathology|other
            $t->foreignId('laboratory_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('collected')->index(); // collected|dispatched|received|rejected
            $t->timestamps();
        });

        Schema::create('surgical_specimen_handovers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_specimen_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_user_id')->constrained('users');
            $t->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('received_by_name')->nullable();
            $t->timestamp('dispatched_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('surgical_implants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('item_name');
            $t->string('batch_number')->nullable();
            $t->string('serial_number')->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('manufacturer')->nullable();
            $t->string('supplier')->nullable();
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
            $t->index(['surgical_case_id']);
        });

        Schema::create('surgical_consumable_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_operation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->decimal('qty_used', 14, 3);
            $t->decimal('qty_returned', 14, 3)->default(0);
            $t->decimal('qty_wasted', 14, 3)->default(0);
            $t->foreignId('stock_issue_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
            $t->unique(['surgical_operation_id', 'inventory_item_id', 'inventory_batch_id'], 'surg_consumable_usage_uniq');
        });

        // ---- Incidents ----
        Schema::create('surgical_incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('surgical_operation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('category'); // intraop_complication|anesthesia_event|postop_complication|equipment|count_discrepancy|medication|other
            $t->timestamp('event_at');
            $t->text('description');
            $t->text('immediate_actions')->nullable();
            $t->string('severity')->default('medium');
            $t->text('escalation')->nullable();
            $t->string('status')->default('open')->index(); // open|under_review|closed
            $t->foreignId('reported_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // ---- Cancellations / postponements ----
        Schema::create('surgical_cancellations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->nullable(); // patient_not_ready|reassessment|patient_decision|theatre|equipment|staff|supplies|administrative|other
            $t->text('reason');
            $t->boolean('patient_notified')->default(false);
            $t->text('notes')->nullable();
            $t->foreignId('cancelled_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('surgical_postponements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surgical_case_id')->constrained()->cascadeOnDelete();
            $t->foreignId('surgical_booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->nullable();
            $t->text('reason');
            $t->date('rescheduled_to')->nullable();
            $t->boolean('patient_notified')->default(false);
            $t->text('notes')->nullable();
            $t->foreignId('postponed_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'surgical_postponements', 'surgical_cancellations', 'surgical_incidents', 'surgical_consumable_usages',
            'surgical_implants', 'surgical_specimen_handovers', 'surgical_specimens', 'surgical_count_items',
            'surgical_counts', 'anesthesia_medication_refs', 'anesthesia_records', 'operative_note_versions',
            'operative_notes', 'surgical_operation_procedures', 'surgical_operation_status_histories',
            'surgical_operations', 'surgical_safety_exceptions', 'surgical_safety_verification_items',
            'surgical_safety_verifications',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
