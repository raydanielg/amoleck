<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'slug')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('name');
                $table->text('description')->nullable();
                $table->boolean('is_protected')->default(false);
                $table->string('status')->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('permissions', 'description')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->text('description')->nullable();
                $table->string('risk_level')->default('low');
                $table->boolean('is_system')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['slug', 'description', 'is_protected', 'status']);
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['description', 'risk_level', 'is_system']);
        });
    }
};
