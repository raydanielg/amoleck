<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->string('category')->nullable();
            $t->text('description')->nullable();
            $t->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('contact')->nullable();
            $t->string('operating_hours')->nullable();
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });

        Schema::create('department_branch', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('operating_hours')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['department_id', 'branch_id']);
        });

        Schema::create('service_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('parent_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $t->unsignedSmallInteger('display_order')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('clinical_services', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name')->index();
            $t->text('description')->nullable();
            $t->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('clinical');
            $t->unsignedSmallInteger('default_duration')->default(30);
            $t->string('clinician_category')->nullable();
            $t->boolean('requires_appointment')->default(false);
            $t->boolean('allows_walkin')->default(true);
            $t->boolean('requires_referral')->default(false);
            $t->boolean('requires_encounter')->default(false);
            $t->string('workflow_mapping')->nullable();
            $t->string('status')->default('draft')->index();
            $t->timestamps();
        });

        Schema::create('service_branch_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->boolean('available')->default(true);
            $t->json('operating_days')->nullable();
            $t->string('operating_hours')->nullable();
            $t->unsignedSmallInteger('duration_override')->nullable();
            $t->timestamps();
            $t->unique(['clinical_service_id', 'branch_id']);
        });

        Schema::create('service_clinician_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('is_primary')->default(false);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('service_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinical_service_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
            $t->index(['clinical_service_id', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['service_versions','service_clinician_assignments','service_branch_configs','clinical_services','service_categories','department_branch','departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
