<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $t) {
            $t->string('allergy_status')->default('not_assessed')->after('status');
        });
        Schema::table('patient_documents', function (Blueprint $t) {
            $t->string('title')->nullable()->after('category');
            $t->date('document_date')->nullable();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('verification_status')->default('unverified');
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('archived_at')->nullable();
        });

        Schema::create('patient_medical_conditions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->date('diagnosed_at')->nullable();
            $t->string('status')->default('active');
            $t->text('treatment_history')->nullable();
            $t->string('source')->default('patient_reported');
            $t->boolean('verified')->default(false);
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['patient_id', 'status']);
        });

        Schema::create('patient_surgical_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('procedure');
            $t->text('description')->nullable();
            $t->string('facility')->nullable();
            $t->date('procedure_at')->nullable();
            $t->string('outcome')->nullable();
            $t->text('complications')->nullable();
            $t->string('source')->default('patient_reported');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('patient_medication_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('medication');
            $t->string('dosage')->nullable();
            $t->string('frequency')->nullable();
            $t->date('started_at')->nullable();
            $t->date('ended_at')->nullable();
            $t->string('status')->default('current');
            $t->string('prescribing_source')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['patient_id', 'status']);
        });

        Schema::create('patient_family_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('relationship');
            $t->string('condition');
            $t->text('notes')->nullable();
            $t->string('source')->default('patient_reported');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('patient_social_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('category');
            $t->text('detail');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('patient_referrals', function (Blueprint $t) {
            $t->id();
            $t->string('ref_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('external');
            $t->date('referral_date');
            $t->foreignId('receiving_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('status')->default('submitted')->index();
            $t->string('referrer_name')->nullable();
            $t->string('referrer_facility')->nullable();
            $t->string('referrer_designation')->nullable();
            $t->string('referrer_contact')->nullable();
            $t->string('referrer_address')->nullable();
            $t->text('reason')->nullable();
            $t->text('presenting_complaint')->nullable();
            $t->text('relevant_history')->nullable();
            $t->text('preliminary_diagnosis')->nullable();
            $t->string('requested_service')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('patient_referral_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_referral_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('patient_document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_document_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('patient_branch_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $t->string('reason')->nullable();
            $t->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamps();
        });

        Schema::create('patient_record_amendments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('record_type');
            $t->unsignedBigInteger('record_id');
            $t->json('changes');
            $t->string('reason');
            $t->foreignId('amended_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        foreach (['patient_record_amendments','patient_branch_transfers','patient_document_versions','patient_referral_status_histories','patient_referrals','patient_social_histories','patient_family_histories','patient_medication_histories','patient_surgical_histories','patient_medical_conditions'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('patient_documents', fn (Blueprint $t) => $t->dropColumns(['title','document_date','verification_status','verified_by','archived_at','branch_id']));
        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn('allergy_status'));
    }
};
