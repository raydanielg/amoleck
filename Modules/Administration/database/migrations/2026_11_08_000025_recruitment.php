<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $t) {
            $t->id();
            $t->string('vacancy_number')->unique();
            $t->string('title');
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('openings')->default(1);
            $t->date('open_date')->nullable();
            $t->date('close_date')->nullable();
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('job_candidates', function (Blueprint $t) {
            $t->id();
            $t->string('candidate_number')->unique();
            $t->foreignId('job_vacancy_id')->constrained()->cascadeOnDelete();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('status')->default('applied')->index();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // link after hire
            $t->timestamps();
            $t->unique(['job_vacancy_id', 'email']);
        });

        Schema::create('candidate_interviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('job_candidate_id')->constrained()->cascadeOnDelete();
            $t->timestamp('scheduled_at');
            $t->string('panel')->nullable();
            $t->string('status')->default('scheduled')->index();
            $t->text('feedback')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('training_courses', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('category')->default('general');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('training_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('enrolled')->index();
            $t->date('completed_at')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->unique(['training_course_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        foreach (['training_enrollments','training_courses','candidate_interviews','job_candidates','job_vacancies'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
