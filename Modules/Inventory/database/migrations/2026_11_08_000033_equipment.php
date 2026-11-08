<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('parent_id')->nullable()->constrained('equipment_categories')->nullOnDelete(); $t->boolean('requires_calibration')->default(false); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('equipment', function (Blueprint $t) {
            $t->id(); $t->string('equipment_number')->unique(); $t->string('name'); $t->foreignId('equipment_category_id')->constrained()->cascadeOnDelete(); $t->string('serial_number')->nullable(); $t->string('model')->nullable(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->unsignedBigInteger('department_id')->nullable()->index(); $t->unsignedBigInteger('finance_asset_id')->nullable()->index(); $t->string('status')->default('active')->index(); $t->string('condition')->default('good'); $t->date('warranty_expiry')->nullable(); $t->date('acquired_date')->nullable(); $t->timestamps();
            $t->unique(['serial_number']);
        });
        Schema::create('equipment_transfers', function (Blueprint $t) {
            $t->id(); $t->foreignId('equipment_id')->constrained()->cascadeOnDelete(); $t->foreignId('from_branch_id')->constrained('branches'); $t->foreignId('to_branch_id')->constrained('branches'); $t->string('status')->default('pending')->index(); $t->foreignId('requested_by')->constrained('users'); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('maintenance_work_orders', function (Blueprint $t) {
            $t->id(); $t->string('wo_number')->unique(); $t->foreignId('equipment_id')->constrained()->cascadeOnDelete(); $t->string('type')->default('corrective'); $t->string('status')->default('open')->index(); $t->date('scheduled_date')->nullable(); $t->text('findings')->nullable(); $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('equipment_calibrations', function (Blueprint $t) {
            $t->id(); $t->foreignId('equipment_id')->constrained()->cascadeOnDelete(); $t->date('calibrated_at'); $t->date('next_due')->nullable(); $t->string('result')->index(); $t->foreignId('performed_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('equipment_downtime', function (Blueprint $t) {
            $t->id(); $t->foreignId('equipment_id')->constrained()->cascadeOnDelete(); $t->timestamp('started_at'); $t->timestamp('ended_at')->nullable(); $t->string('reason')->nullable(); $t->timestamps();
        });
        Schema::create('equipment_retirements', function (Blueprint $t) {
            $t->id(); $t->foreignId('equipment_id')->constrained()->cascadeOnDelete(); $t->string('reason'); $t->string('status')->default('requested')->index(); $t->foreignId('requested_by')->constrained('users'); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['equipment_retirements','equipment_downtime','equipment_calibrations','maintenance_work_orders','equipment_transfers','equipment','equipment_categories'] as $t) { Schema::dropIfExists($t); }
    }
};
