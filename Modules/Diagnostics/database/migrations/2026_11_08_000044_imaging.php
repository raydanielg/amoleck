<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imaging_requests', function (Blueprint $t) {
            $t->id(); $t->string('request_number')->unique(); $t->unsignedBigInteger('patient_id')->index(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete(); $t->string('modality')->default('xray'); $t->string('body_region')->nullable(); $t->string('priority')->default('routine'); $t->text('clinical_indication')->nullable(); $t->string('status')->default('requested')->index(); $t->foreignId('ordered_by')->constrained('users'); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('reject_reason')->nullable(); $t->timestamps();
        });
        Schema::create('imaging_procedures', function (Blueprint $t) {
            $t->id(); $t->foreignId('imaging_request_id')->constrained()->cascadeOnDelete(); $t->foreignId('equipment_id')->nullable()->index(); $t->string('status')->default('scheduled')->index(); $t->timestamp('scheduled_at')->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
            $t->unique(['imaging_request_id', 'equipment_id']);
        });
        Schema::create('imaging_studies', function (Blueprint $t) {
            $t->id(); $t->string('study_uid')->unique(); $t->foreignId('imaging_procedure_id')->constrained()->cascadeOnDelete(); $t->string('pacs_reference')->nullable(); $t->integer('series_count')->default(0); $t->string('status')->default('acquired'); $t->timestamps();
        });
        Schema::create('radiology_reports', function (Blueprint $t) {
            $t->id(); $t->string('report_number')->unique(); $t->foreignId('imaging_request_id')->constrained()->cascadeOnDelete(); $t->foreignId('imaging_study_id')->nullable()->constrained()->nullOnDelete(); $t->text('findings')->nullable(); $t->text('impression')->nullable(); $t->string('status')->default('draft')->index(); $t->foreignId('radiologist_id')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('authorized_at')->nullable(); $t->integer('version')->default(1); $t->foreignId('supersedes_id')->nullable()->constrained('radiology_reports')->nullOnDelete(); $t->timestamps();
            $t->unique(['imaging_request_id', 'version']);
        });
    }
    public function down(): void
    {
        foreach (['radiology_reports','imaging_studies','imaging_procedures','imaging_requests'] as $t) { Schema::dropIfExists($t); }
    }
};
