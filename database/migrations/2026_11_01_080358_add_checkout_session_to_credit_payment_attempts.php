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
        Schema::table('credit_payment_attempts', function (Blueprint $table) {
            $table->string('paymongo_checkout_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('paymongo_payment_id')->nullable()->unique();
            $table->timestamp('last_checked_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_payment_attempts', function (Blueprint $table) {
            $table->dropUnique(['paymongo_checkout_session_id']);
            $table->dropUnique(['paymongo_payment_id']);
            $table->dropIndex(['last_checked_at']);
            $table->dropColumn(['paymongo_checkout_session_id', 'checkout_url', 'paymongo_payment_id', 'last_checked_at']);
        });
    }
};
