<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('phone_no', 100)->unique();
            $table->string('name', 100)->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'customer'])->default('customer');
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('unit')->nullable();
            $table->string('barangay')->nullable();
            $table->boolean('credit_approved')->default(false);
            $table->boolean('credit_on_hold')->default(false);
            $table->decimal('credit_limit', 10, 2)->nullable();
            $table->string('credit_due_day', 10)->nullable();
            $table->rememberToken();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
