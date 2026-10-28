<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 150);
            $table->string('middle_name', 150)->nullable();
            $table->string('last_name', 150);
            $table->string('phone_number', 30);
            $table->text('province');
            $table->text('city');
            $table->text('barangay');
            $table->text('street');
            $table->string('credit_due_day', 10)->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->string('id_type', 100);
            $table->string('id_photo_path');
            $table->string('selfie_photo_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('privacy_notice_version', 30)->nullable();
            $table->timestamp('privacy_notice_accepted_at')->nullable();
            $table->string('terms_version', 30)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
