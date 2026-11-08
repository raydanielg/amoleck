<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('outpatient');
            $t->string('status')->default('draft')->index();
            $t->date('issue_date')->nullable();
            $t->date('due_date')->nullable();
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->string('currency', 3)->default('TZS');
            $t->string('cancel_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['patient_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_charge_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('service_name');
            $t->timestamp('service_date');
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('unit_price', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2);
            $t->timestamps();
        });

        Schema::create('invoice_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->string('credit_number')->unique();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('partial');
            $t->decimal('amount', 12, 2);
            $t->string('reason');
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['credit_notes','invoice_status_histories','invoice_items','invoices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
