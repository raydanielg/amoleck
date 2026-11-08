<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->string('status')->default('draft')->index();
            $t->timestamps();
        });

        Schema::create('performance_kpis', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('category')->default('general');
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('weight', 5, 2)->default(1);
            $t->decimal('target', 14, 2)->nullable();
            $t->string('unit')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('employee_objectives', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('performance_kpi_id')->constrained()->cascadeOnDelete();
            $t->decimal('target', 14, 2)->nullable();
            $t->date('due_date')->nullable();
            $t->string('status')->default('assigned')->index();
            $t->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $t) {
            $t->id();
            $t->string('review_number')->unique();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('performance_cycle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('self_score', 5, 2)->nullable();
            $t->decimal('supervisor_score', 5, 2)->nullable();
            $t->decimal('final_score', 5, 2)->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('comments')->nullable();
            $t->text('disagreement')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employee_id', 'performance_cycle_id']);
        });

        Schema::create('performance_improvement_plans', function (Blueprint $t) {
            $t->id();
            $t->string('pip_number')->unique();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('performance_review_id')->nullable()->constrained()->nullOnDelete();
            $t->text('objective')->nullable();
            $t->date('start_date')->nullable();
            $t->date('review_date')->nullable();
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['performance_improvement_plans','performance_reviews','employee_objectives','performance_kpis','performance_cycles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
