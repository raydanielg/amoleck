<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('emergency_dispositions', function (Blueprint $t) {
            $t->id(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->string('type')->index(); $t->text('clinical_summary')->nullable(); $t->string('status')->default('proposed')->index(); $t->foreignId('proposed_by')->constrained('users'); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
            $t->unique(['emergency_visit_id', 'status']);
        });
        Schema::create('emergency_discharges', function (Blueprint $t) {
            $t->id(); $t->string('discharge_number')->unique(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->string('type')->default('home'); $t->text('summary')->nullable(); $t->string('status')->default('pending')->index(); $t->foreignId('discharged_by')->constrained('users'); $t->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('emergency_transfers', function (Blueprint $t) {
            $t->id(); $t->string('transfer_number')->unique(); $t->foreignId('emergency_visit_id')->constrained()->cascadeOnDelete(); $t->string('destination')->nullable(); $t->string('type')->default('internal'); $t->string('status')->default('pending')->index(); $t->foreignId('requested_by')->constrained('users'); $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('received_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { foreach (['emergency_transfers','emergency_discharges','emergency_dispositions'] as $t) { Schema::dropIfExists($t); } }
};
