<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('mrn')->unique();
            $table->string('first_name')->index();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->index();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('photo')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('alt_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('registration_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('primary_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('registration_source')->nullable();
            $table->string('referred_by')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('patient_identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('number')->index();
            $table->string('issuing_country')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'number']);
        });

        Schema::create('patient_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('alt_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('event')->default('associated');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'branch_id']);
        });

        Schema::create('patient_medical_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->text('description');
            $table->string('source')->default('patient_reported');
            $table->boolean('verified')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('other')->index();
            $table->string('description')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('granted');
            $table->date('consented_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_path')->nullable();
            $table->date('withdrawn_at')->nullable();
            $table->string('withdrawal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('patient_duplicate_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matched_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('matched_on')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['patient_duplicate_reviews','patient_status_histories','patient_consents','patient_documents','patient_medical_entries','patient_branches','patient_guardians','patient_emergency_contacts','patient_identifications','patients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
