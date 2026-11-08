<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('category')->nullable(); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('status')->default('pending')->index(); $t->timestamps();
        });
        Schema::create('stock_requisitions', function (Blueprint $t) {
            $t->id(); $t->string('requisition_number')->unique(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->decimal('qty', 14, 3); $t->string('status')->default('draft')->index(); $t->text('justification')->nullable(); $t->foreignId('requested_by')->constrained('users'); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id(); $t->string('po_number')->unique(); $t->foreignId('supplier_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->foreignId('stock_requisition_id')->nullable()->constrained()->nullOnDelete(); $t->decimal('qty', 14, 3); $t->decimal('unit_price', 14, 2)->nullable(); $t->decimal('received_qty', 14, 3)->default(0); $t->string('status')->default('draft')->index(); $t->foreignId('created_by')->constrained('users'); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('replenishment_suggestions', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->decimal('available', 14, 3); $t->decimal('outgoing_reserved', 14, 3); $t->decimal('suggested_qty', 14, 3); $t->string('status')->default('suggested')->index(); $t->timestamps();
            $t->unique(['inventory_item_id', 'warehouse_id', 'status']);
        });
    }
    public function down(): void
    {
        foreach (['replenishment_suggestions','purchase_orders','stock_requisitions','suppliers'] as $t) { Schema::dropIfExists($t); }
    }
};
