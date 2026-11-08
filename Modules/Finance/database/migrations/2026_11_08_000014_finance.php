<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_service_id')->constrained()->cascadeOnDelete();
            $t->string('service_name');
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category')->nullable();
            $t->decimal('price', 12, 2);
            $t->string('currency', 3)->default('TZS');
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->string('status')->default('draft')->index();
            $t->string('reject_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['clinical_service_id', 'branch_id', 'status']);
        });

        Schema::create('patient_charges', function (Blueprint $t) {
            $t->id();
            $t->string('charge_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('clinical_service_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source')->index();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('service_price_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('service_date');
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('unit_price', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('tax', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->string('currency', 3)->default('TZS');
            $t->string('description')->nullable();
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->unique(['source', 'source_id']);
            $t->index(['patient_id', 'status']);
        });

        Schema::create('charge_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_charge_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('charge_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_charge_id')->constrained()->cascadeOnDelete();
            $t->string('type')->index();
            $t->decimal('amount', 12, 2);
            $t->string('reason');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['charge_adjustments','charge_status_histories','patient_charges','service_prices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
