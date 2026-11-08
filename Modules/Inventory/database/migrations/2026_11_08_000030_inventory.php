<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('type')->default('general'); $t->foreignId('parent_id')->nullable()->constrained('inventory_categories')->nullOnDelete(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->string('type')->default('main'); $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('inventory_category_id')->constrained()->cascadeOnDelete(); $t->string('uom')->default('piece'); $t->string('tracking')->default('batch'); $t->unsignedInteger('reorder_level')->nullable(); $t->unsignedInteger('reorder_qty')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('inventory_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->string('batch_number')->nullable(); $t->date('expiry_date')->nullable(); $t->decimal('qty', 14, 3)->default(0); $t->decimal('reserved', 14, 3)->default(0); $t->decimal('unit_cost', 14, 2)->nullable(); $t->string('status')->default('available')->index(); $t->timestamps();
            $t->unique(['inventory_item_id', 'warehouse_id', 'batch_number']);
        });
        Schema::create('stock_receipts', function (Blueprint $t) {
            $t->id(); $t->string('receipt_number')->unique(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->string('source')->default('po'); $t->string('status')->default('draft')->index(); $t->foreignId('received_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('stock_issues', function (Blueprint $t) {
            $t->id(); $t->string('issue_number')->unique(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete(); $t->decimal('qty', 14, 3); $t->string('status')->default('draft')->index(); $t->foreignId('issued_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id(); $t->string('transfer_number')->unique(); $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete(); $t->foreignId('from_warehouse_id')->constrained('warehouses'); $t->foreignId('to_warehouse_id')->constrained('warehouses'); $t->decimal('qty', 14, 3); $t->string('status')->default('draft')->index(); $t->foreignId('created_by')->constrained('users'); $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('inventory_ledger', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_item_id')->constrained()->cascadeOnDelete(); $t->foreignId('inventory_batch_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete(); $t->string('type')->index(); $t->decimal('qty', 14, 3); $t->decimal('balance', 14, 3); $t->string('source_type')->nullable(); $t->unsignedBigInteger('source_id')->nullable(); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
            $t->unique(['source_type', 'source_id', 'type']);
        });
        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete(); $t->decimal('system_qty', 14, 3); $t->decimal('counted_qty', 14, 3); $t->decimal('difference', 14, 3); $t->string('reason'); $t->string('status')->default('draft')->index(); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('stock_reservations', function (Blueprint $t) {
            $t->id(); $t->foreignId('inventory_batch_id')->constrained()->cascadeOnDelete(); $t->decimal('qty', 14, 3); $t->string('status')->default('reserved')->index(); $t->string('reason')->nullable(); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['stock_reservations','stock_adjustments','inventory_ledger','stock_transfers','stock_issues','stock_receipts','inventory_batches','inventory_items','warehouses','inventory_categories'] as $t) { Schema::dropIfExists($t); }
    }
};
