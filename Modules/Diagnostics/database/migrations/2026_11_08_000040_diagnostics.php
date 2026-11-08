<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_test_categories', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('parent_id')->nullable()->constrained('diagnostic_test_categories')->nullOnDelete(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('diagnostic_specimens', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->string('container')->nullable(); $t->text('collection_instructions')->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('diagnostic_tests', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->foreignId('diagnostic_test_category_id')->constrained()->cascadeOnDelete(); $t->foreignId('diagnostic_specimen_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); $t->string('test_type')->default('laboratory'); $t->unsignedInteger('tat_minutes')->nullable(); $t->boolean('requires_fasting')->default(false); $t->boolean('requires_external')->default(false); $t->boolean('requires_approval')->default(false); $t->boolean('active')->default(true); $t->integer('version')->default(1); $t->timestamps();
        });
        Schema::create('diagnostic_test_parameters', function (Blueprint $t) {
            $t->id(); $t->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete(); $t->string('code'); $t->string('name'); $t->string('data_type')->default('numeric'); $t->string('unit')->nullable(); $t->unsignedTinyInteger('sort_order')->default(0); $t->boolean('required')->default(true); $t->timestamps();
            $t->unique(['diagnostic_test_id', 'code']);
        });
        Schema::create('diagnostic_reference_ranges', function (Blueprint $t) {
            $t->id(); $t->foreignId('diagnostic_test_parameter_id')->constrained()->cascadeOnDelete(); $t->decimal('low', 14, 4)->nullable(); $t->decimal('high', 14, 4)->nullable(); $t->string('applies_to')->default('all'); $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->string('source')->nullable(); $t->timestamps();
        });
        Schema::create('diagnostic_branch_tests', function (Blueprint $t) {
            $t->id(); $t->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->boolean('active')->default(true); $t->timestamps();
            $t->unique(['diagnostic_test_id', 'branch_id']);
        });
        Schema::create('diagnostic_staff_assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('employee_id')->constrained()->cascadeOnDelete(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); $t->string('role')->default('technician'); $t->boolean('active')->default(true); $t->timestamps();
            $t->unique(['employee_id', 'branch_id', 'department_id', 'role']);
        });
    }
    public function down(): void
    {
        foreach (['diagnostic_staff_assignments','diagnostic_branch_tests','diagnostic_reference_ranges','diagnostic_test_parameters','diagnostic_tests','diagnostic_specimens','diagnostic_test_categories'] as $t) { Schema::dropIfExists($t); }
    }
};
