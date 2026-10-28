<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('credit_term_months')->nullable();
            $table->decimal('credit_interest_rate', 5, 2)->nullable();
            $table->decimal('credit_finance_charge', 10, 2)->default(0);
        });

        Schema::create('credit_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 10, 2);
            $table->unique(['order_id', 'installment_number']);
        });

        Schema::table('credit_payments', function (Blueprint $table) {
            $table->foreignId('credit_installment_id')
                ->nullable()
                ->constrained('credit_installments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_payments', function (Blueprint $table) {
            $table->dropForeign(['credit_installment_id']);
            $table->dropColumn('credit_installment_id');
        });

        Schema::dropIfExists('credit_installments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'credit_term_months',
                'credit_interest_rate',
                'credit_finance_charge',
            ]);
        });
    }
};