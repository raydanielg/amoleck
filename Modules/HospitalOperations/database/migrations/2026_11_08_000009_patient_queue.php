<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_types', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
            $t->string('department')->nullable();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['code', 'branch_id']);
        });

        Schema::create('patient_visits', function (Blueprint $t) {
            $t->id();
            $t->string('visit_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source')->default('walk_in')->index();
            $t->string('status')->default('expected')->index();
            $t->string('reason')->nullable();
            $t->timestamp('arrived_at')->nullable();
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamp('service_started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('outcome')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['branch_id', 'created_at']);
        });

        Schema::create('queue_entries', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_number')->unique();
            $t->foreignId('patient_visit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('queue_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinician_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('priority')->default('normal')->index();
            $t->string('status')->default('waiting')->index();
            $t->timestamp('called_at')->nullable();
            $t->foreignId('called_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('skip_reason')->nullable();
            $t->timestamps();
            $t->index(['queue_type_id', 'status', 'priority']);
        });

        Schema::create('queue_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('queue_entry_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('queue_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('queue_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_queue_type_id')->constrained('queue_types')->cascadeOnDelete();
            $t->foreignId('to_queue_type_id')->constrained('queue_types')->cascadeOnDelete();
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['queue_transfers','queue_status_histories','queue_entries','patient_visits','queue_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
