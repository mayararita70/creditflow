<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->bigInteger('amount_cents');
            $table->string('idempotency_key', 100)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('transactions');
            $table->timestamps();

            $table->unique(['account_id', 'idempotency_key']);
            $table->index(['account_id', 'created_at']);
        });

        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount_cents > 0)');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_valid CHECK (type IN ('credit', 'debit', 'reversal'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
