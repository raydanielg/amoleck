<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_catalogues', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('generic_name')->index();
            $t->string('brand_name')->nullable();
            $t->string('category')->nullable();
            $t->string('dosage_form');
            $t->string('strength')->nullable();
            $t->string('strength_unit')->nullable();
            $t->json('routes')->nullable();
            $t->boolean('controlled')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('medication_orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->unique();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ward_id')->nullable()->constrained('medication_catalogues')->nullOnDelete();
            $t->foreignId('medication_catalogue_id')->nullable()->constrained()->nullOnDelete();
            $t->string('generic_name');
            $t->string('brand_name')->nullable();
            $t->string('dose');
            $t->string('dose_unit');
            $t->string('route');
            $t->string('frequency');
            $t->json('schedule_times')->nullable();
            $t->string('type')->default('regular')->index();
            $t->unsignedSmallInteger('prn_min_interval_minutes')->nullable();
            $t->unsignedSmallInteger('prn_max_frequency')->nullable();
            $t->text('instructions')->nullable();
            $t->text('indication')->nullable();
            $t->string('status')->default('draft')->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('discontinued_at')->nullable();
            $t->string('discontinue_reason')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['admission_id', 'status']);
        });

        Schema::create('medication_order_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medication_order_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->string('action');
            $t->string('reason')->nullable();
            $t->foreignId('acted_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('medication_scheduled_doses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medication_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->timestamp('scheduled_at');
            $t->string('status')->default('scheduled')->index();
            $t->timestamps();
            $t->index(['scheduled_at', 'status']);
        });

        Schema::create('medication_administrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medication_scheduled_dose_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('medication_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $t->string('actual_dose');
            $t->string('actual_route');
            $t->timestamp('administered_at');
            $t->foreignId('administered_by')->constrained('users');
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('administered')->index();
            $t->string('reason')->nullable();
            $t->text('notes')->nullable();
            $t->text('patient_response')->nullable();
            $t->timestamps();
            $t->unique(['medication_scheduled_dose_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['medication_administrations','medication_scheduled_doses','medication_order_versions','medication_orders','medication_catalogues'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
