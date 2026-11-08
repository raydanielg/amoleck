<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_key_unique');
            $table->foreignId('branch_id')->nullable()->after('key')->constrained('branches')->cascadeOnDelete();
            $table->unique(['key', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->unique('key');
        });
    }
};
