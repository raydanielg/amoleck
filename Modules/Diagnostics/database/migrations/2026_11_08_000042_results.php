<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_processing_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('laboratory_specimen_id')->constrained()->cascadeOnDelete(); $t->string('event')->index(); $t->foreignId('equipment_id')->nullable()->index(); $t->foreignId('performed_by')->constrained('users'); $t->text('notes')->nullable(); $t->timestamps();
            $t->unique(['laboratory_specimen_id', 'event']);
        });
        Schema::create('laboratory_aliquots', function (Blueprint $t) {
            $t->id(); $t->string('aliquot_number')->unique(); $t->foreignId('laboratory_specimen_id')->constrained()->cascadeOnDelete(); $t->decimal('volume', 8, 2)->nullable(); $t->string('status')->default('stored'); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('laboratory_test_results', function (Blueprint $t) {
            $t->id(); $t->string('result_number')->unique(); $t->foreignId('laboratory_order_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('laboratory_specimen_id')->nullable()->constrained()->nullOnDelete(); $t->string('status')->default('entered')->index(); $t->foreignId('entered_by')->constrained('users'); $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete(); $t->text('comments')->nullable(); $t->timestamps();
            $t->unique(['laboratory_order_item_id']);
        });
        Schema::create('laboratory_result_values', function (Blueprint $t) {
            $t->id(); $t->foreignId('laboratory_test_result_id')->constrained()->cascadeOnDelete(); $t->foreignId('diagnostic_test_parameter_id')->constrained()->cascadeOnDelete(); $t->string('value')->nullable(); $t->string('flag')->nullable(); $t->foreignId('diagnostic_reference_range_id')->nullable()->constrained()->nullOnDelete(); $t->timestamps();
            $t->unique(['laboratory_test_result_id', 'diagnostic_test_parameter_id']);
        });
        Schema::create('laboratory_critical_results', function (Blueprint $t) {
            $t->id(); $t->foreignId('laboratory_test_result_id')->constrained()->cascadeOnDelete(); $t->foreignId('laboratory_result_value_id')->constrained()->cascadeOnDelete(); $t->string('status')->default('pending')->index(); $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('acknowledged_at')->nullable(); $t->timestamps();
        });
        Schema::create('laboratory_qc_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete(); $t->foreignId('laboratory_specimen_id')->nullable()->constrained()->nullOnDelete(); $t->string('result')->index(); $t->foreignId('performed_by')->constrained('users'); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['laboratory_qc_runs','laboratory_critical_results','laboratory_result_values','laboratory_test_results','laboratory_aliquots','laboratory_processing_events'] as $t) { Schema::dropIfExists($t); }
    }
};
