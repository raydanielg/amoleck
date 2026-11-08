<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_orders', function (Blueprint $t) {
            $t->id(); $t->string('order_number')->unique(); $t->unsignedBigInteger('patient_id')->index(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->foreignId('ordered_by')->constrained('users'); $t->string('priority')->default('routine'); $t->string('status')->default('draft')->index(); $t->text('clinical_indication')->nullable(); $t->string('reject_reason')->nullable(); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('laboratory_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('laboratory_order_id')->constrained()->cascadeOnDelete(); $t->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete(); $t->integer('test_version'); $t->string('status')->default('pending')->index(); $t->timestamps();
            $t->unique(['laboratory_order_id', 'diagnostic_test_id']);
        });
        Schema::create('laboratory_specimens', function (Blueprint $t) {
            $t->id(); $t->string('specimen_number')->unique(); $t->foreignId('laboratory_order_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('diagnostic_specimen_id')->constrained()->cascadeOnDelete(); $t->string('status')->default('pending')->index(); $t->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('collected_at')->nullable(); $t->string('reject_reason')->nullable(); $t->foreignId('recalled_from_id')->nullable()->constrained('laboratory_specimens')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('laboratory_accessions', function (Blueprint $t) {
            $t->id(); $t->string('accession_number')->unique(); $t->foreignId('laboratory_specimen_id')->constrained()->cascadeOnDelete(); $t->foreignId('received_by')->constrained('users'); $t->timestamps();
            $t->unique(['laboratory_specimen_id']);
        });
    }
    public function down(): void
    {
        foreach (['laboratory_accessions','laboratory_specimens','laboratory_order_items','laboratory_orders'] as $t) { Schema::dropIfExists($t); }
    }
};
