<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_locations', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('dispensings', function (Blueprint $t) {
            $t->id(); $t->string('dispense_number')->unique(); $t->foreignId('pharmacy_location_id')->constrained()->cascadeOnDelete(); $t->foreignId('medication_order_id')->nullable()->index(); $t->unsignedBigInteger('patient_id')->nullable()->index(); $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); $t->string('status')->default('pending_review')->index(); $t->foreignId('pharmacist_id')->nullable()->constrained('users')->nullOnDelete(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('dispensing_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('dispensing_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete(); $t->decimal('ordered_qty', 14, 3); $t->decimal('dispensed_qty', 14, 3); $t->decimal('remaining_qty', 14, 3); $t->timestamps();
        });
        Schema::create('medication_returns', function (Blueprint $t) {
            $t->id(); $t->string('return_number')->unique(); $t->foreignId('dispensing_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete(); $t->decimal('qty', 14, 3); $t->string('reason'); $t->string('disposition')->default('quarantine')->index(); $t->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['medication_returns','dispensing_lines','dispensings','pharmacy_locations'] as $t) { Schema::dropIfExists($t); }
    }
};
