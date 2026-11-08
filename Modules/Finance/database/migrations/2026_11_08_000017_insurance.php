<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_providers', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('contact')->nullable();
            $t->string('email')->nullable();
            $t->string('claims_contact')->nullable();
            $t->string('status')->default('draft')->index();
            $t->timestamps();
        });

        Schema::create('insurance_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->decimal('coverage_percent', 5, 2)->default(0);
            $t->decimal('copay_fixed', 12, 2)->nullable();
            $t->decimal('annual_limit', 12, 2)->nullable();
            $t->decimal('per_visit_limit', 12, 2)->nullable();
            $t->boolean('requires_preauth')->default(false);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('patient_insurance_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('insurance_provider_id')->constrained()->cascadeOnDelete();
            $t->foreignId('insurance_plan_id')->constrained()->cascadeOnDelete();
            $t->string('member_number')->index();
            $t->string('policy_number')->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->string('status')->default('pending')->index();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['patient_id', 'status']);
        });

        Schema::create('insurance_preauthorizations', function (Blueprint $t) {
            $t->id();
            $t->string('auth_number')->unique();
            $t->foreignId('patient_insurance_membership_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('clinical_service_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('estimated_cost', 12, 2)->nullable();
            $t->string('status')->default('draft')->index();
            $t->decimal('approved_amount', 12, 2)->nullable();
            $t->unsignedSmallInteger('approved_sessions')->nullable();
            $t->string('provider_ref')->nullable();
            $t->text('justification')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('insurance_claims', function (Blueprint $t) {
            $t->id();
            $t->string('claim_number')->unique();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_insurance_membership_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->decimal('patient_share', 12, 2)->default(0);
            $t->decimal('insurer_share', 12, 2);
            $t->decimal('approved_amount', 12, 2)->nullable();
            $t->decimal('paid_amount', 12, 2)->default(0);
            $t->string('status')->default('draft')->index();
            $t->timestamp('submitted_at')->nullable();
            $t->string('submit_method')->nullable();
            $t->string('reject_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['invoice_id', 'patient_insurance_membership_id']);
        });

        Schema::create('corporate_clients', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('contact')->nullable();
            $t->string('email')->nullable();
            $t->decimal('credit_limit', 14, 2)->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(30);
            $t->string('status')->default('draft')->index();
            $t->timestamps();
        });

        Schema::create('corporate_sponsorships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('corporate_client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('employee_number')->nullable();
            $t->decimal('coverage_percent', 5, 2)->default(100);
            $t->decimal('benefit_limit', 12, 2)->nullable();
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->string('status')->default('pending')->index();
            $t->timestamps();
            $t->unique(['corporate_client_id', 'patient_id']);
        });
    }

    public function down(): void
    {
        foreach (['corporate_sponsorships','corporate_clients','insurance_claims','insurance_preauthorizations','patient_insurance_memberships','insurance_plans','insurance_providers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
