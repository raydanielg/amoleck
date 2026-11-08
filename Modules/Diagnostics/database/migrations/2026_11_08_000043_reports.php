<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_reports', function (Blueprint $t) {
            $t->id(); $t->string('report_number')->unique(); $t->foreignId('laboratory_test_result_id')->constrained()->cascadeOnDelete(); $t->integer('version')->default(1); $t->foreignId('supersedes_id')->nullable()->constrained('diagnostic_reports')->nullOnDelete(); $t->string('status')->default('pending_release')->index(); $t->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('released_at')->nullable(); $t->timestamps();
        });
        Schema::create('diagnostic_clinician_reviews', function (Blueprint $t) {
            $t->id(); $t->foreignId('diagnostic_report_id')->constrained()->cascadeOnDelete(); $t->foreignId('reviewed_by')->constrained('users'); $t->text('comments')->nullable(); $t->string('follow_up')->nullable(); $t->timestamps();
            $t->unique(['diagnostic_report_id', 'reviewed_by']);
        });
    }
    public function down(): void
    {
        foreach (['diagnostic_clinician_reviews','diagnostic_reports'] as $t) { Schema::dropIfExists($t); }
    }
};
