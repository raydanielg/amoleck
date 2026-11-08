<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurse_patient_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('nurse_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('primary');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->foreignId('assigned_by')->constrained('users');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('vital_signs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind')->index();
            $t->decimal('value_num', 8, 2)->nullable();
            $t->string('value_text')->nullable();
            $t->string('unit');
            $t->string('method')->nullable();
            $t->string('source')->default('manual');
            $t->timestamp('taken_at');
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users');
            $t->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('correction_reason')->nullable();
            $t->timestamps();
            $t->index(['patient_id', 'kind', 'taken_at']);
        });

        Schema::create('nursing_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('nurse_id')->constrained('users');
            $t->string('category')->default('routine');
            $t->string('shift')->nullable();
            $t->text('subjective')->nullable();
            $t->text('objective')->nullable();
            $t->text('assessment')->nullable();
            $t->text('actions')->nullable();
            $t->text('response')->nullable();
            $t->string('status')->default('draft')->index();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
        });

        Schema::create('nursing_note_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('nursing_note_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->string('reason')->nullable();
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('fluid_balance_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->string('direction')->index();
            $t->string('category');
            $t->decimal('amount', 8, 2);
            $t->string('unit')->default('ml');
            $t->timestamp('recorded_at');
            $t->string('shift')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users');
            $t->timestamps();
            $t->index(['admission_id', 'direction']);
        });

        Schema::create('clinical_escalations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->text('concern');
            $t->string('observation')->nullable();
            $t->foreignId('reported_by')->constrained('users');
            $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('contact_method')->nullable();
            $t->timestamp('escalated_at');
            $t->timestamp('acknowledged_at')->nullable();
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('instructions')->nullable();
            $t->string('status')->default('open')->index();
            $t->timestamps();
        });

        Schema::create('nursing_handovers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ward_id')->nullable()->constrained()->nullOnDelete();
            $t->string('outgoing_shift')->nullable();
            $t->string('incoming_shift')->nullable();
            $t->text('summary')->nullable();
            $t->json('patients')->nullable();
            $t->string('status')->default('draft')->index();
            $t->foreignId('prepared_by')->constrained('users');
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['nursing_handovers','clinical_escalations','fluid_balance_entries','nursing_note_versions','nursing_notes','vital_signs','nurse_patient_assignments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
