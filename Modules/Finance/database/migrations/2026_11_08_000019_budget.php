<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('name');
            $t->foreignId('accounting_period_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('operating');
            $t->string('status')->default('draft')->index();
            $t->decimal('total', 14, 2)->default(0);
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reject_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chart_of_account_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('category')->nullable();
            $t->string('description')->nullable();
            $t->decimal('annual_amount', 14, 2);
            $t->decimal('revised_amount', 14, 2)->nullable();
            $t->timestamps();
            $t->index(['budget_id', 'department_id']);
        });

        Schema::create('budget_commitments', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->foreignId('budget_line_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 14, 2);
            $t->string('status')->default('approved')->index(); // approved|released|fulfilled
            $t->string('source')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['source', 'source_id']);
        });

        Schema::create('purchase_requisitions', function (Blueprint $t) {
            $t->id();
            $t->string('requisition_number')->unique();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('budget_line_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('estimated_amount', 14, 2);
            $t->string('financial_status')->default('not_checked')->index();
            $t->string('status')->default('draft')->index();
            $t->text('justification')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('financial_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['purchase_requisitions','budget_commitments','budget_lines','budgets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
