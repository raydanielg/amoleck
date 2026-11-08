<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('slogan')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('license_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('postal_code')->nullable();
            $table->json('working_hours')->nullable();
            $table->string('timezone')->default('Africa/Dar_es_Salaam');
            $table->string('currency')->default('TZS');
            $table->text('report_header')->nullable();
            $table->text('report_footer')->nullable();
            $table->string('stamp')->nullable();
            $table->json('signatories')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_profiles');
    }
};
