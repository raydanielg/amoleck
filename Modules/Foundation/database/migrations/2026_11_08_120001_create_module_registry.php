<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('category')->default('core');
            $table->string('icon')->nullable();
            $table->string('status')->default('pending_installation')->index();
            $table->json('dependencies')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_protected')->default(false);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('module_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['module_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_role');
        Schema::dropIfExists('modules');
    }
};
