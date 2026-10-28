<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('method', 20); // qrph, gcash, or paymaya
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('PHP');

            $table->string('status', 20)->default('creating')->index();
            $table->string('paymongo_payment_intent_id')->nullable()->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('credit_payment_attempt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_payment_attempt_id')
                ->constrained('credit_payment_attempts')
                ->cascadeOnDelete();
            $table->foreignId('credit_installment_id')
                ->constrained('credit_installments')
                ->cascadeOnDelete();

            $table->unsignedInteger('amount_cents');

            $table->unique([
                'credit_payment_attempt_id',
                'credit_installment_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_payment_attempts');
        Schema::dropIfExists('credit_payment_attempt_allocations');
    }
};
