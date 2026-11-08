<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
            $t->string('type')->default('cash');
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['code', 'branch_id']);
        });

        Schema::create('payment_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('payment_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $t->string('method')->index();
            $t->string('provider')->nullable();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('TZS');
            $t->string('reference')->nullable();
            $t->string('status')->default('initiated')->index();
            $t->timestamp('initiated_at');
            $t->timestamp('confirmed_at')->nullable();
            $t->string('idempotency_key')->nullable()->unique();
            $t->foreignId('recorded_by')->constrained('users');
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['invoice_id', 'status']);
        });

        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('status')->default('allocated')->index();
            $t->string('reversal_reason')->nullable();
            $t->foreignId('allocated_by')->constrained('users');
            $t->timestamps();
            $t->unique(['payment_transaction_id', 'invoice_id']);
        });

        Schema::create('payment_refunds', function (Blueprint $t) {
            $t->id();
            $t->string('refund_number')->unique();
            $t->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('reason');
            $t->string('method')->nullable();
            $t->string('status')->default('pending_approval')->index();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('cashier_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->decimal('opening_balance', 12, 2)->default(0);
            $t->decimal('expected_closing', 12, 2)->default(0);
            $t->decimal('actual_closing', 12, 2)->nullable();
            $t->decimal('variance', 12, 2)->nullable();
            $t->string('status')->default('open')->index();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['cashier_shifts','payment_refunds','payment_allocations','payment_transactions','payment_methods'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
