<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type')->index(); // asset|liability|equity|revenue|expense
            $t->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->boolean('posting')->default(true);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('starts_at');
            $t->date('ends_at');
            $t->string('status')->default('open')->index(); // open|closed
            $t->timestamps();
            $t->unique(['starts_at', 'ends_at']);
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->string('journal_number')->unique();
            $t->date('journal_date');
            $t->foreignId('accounting_period_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('general');
            $t->string('description');
            $t->string('source')->nullable()->index(); // invoice|payment|expense|credit_note|refund
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('status')->default('draft')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->unique(['source', 'source_id']);
            $t->index(['accounting_period_id', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chart_of_account_id')->constrained()->cascadeOnDelete();
            $t->string('direction')->index(); // debit|credit
            $t->decimal('amount', 14, 2);
            $t->string('memo')->nullable();
            $t->timestamps();
            $t->index(['chart_of_account_id', 'direction']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('expense_number')->unique();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chart_of_account_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category');
            $t->string('payee')->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('status')->default('draft')->index();
            $t->date('expense_date');
            $t->text('description')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['expenses','journal_lines','journal_entries','accounting_periods','chart_of_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
