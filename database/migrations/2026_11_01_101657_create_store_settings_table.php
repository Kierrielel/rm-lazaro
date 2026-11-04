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
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name', 150);
            $table->text('store_address');
            $table->string('contact_phone', 30);
            $table->string('contact_email', 100)->nullable();
            $table->string('opening_hours', 255);
            $table->unsignedInteger('low_stock_threshold')->default(20);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('store_settings')->insert([
            'id' => 1,
            'store_name' => 'RM LAZARO ENTERPRISES',
            'store_address' => '4XPH+86P, Magsaysay Rd, San Miguel, Bulacan',
            'contact_phone' => '0917 982 8985',
            'contact_email' => null,
            'opening_hours' => 'Monday - Saturday: 7:00 AM - 5:00 PM',
            'low_stock_threshold' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
