<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->changeStatuses(['pending', 'processing', 'out_for_delivery', 'completed', 'cancelled']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->where('order_status', 'out_for_delivery')->update(['order_status' => 'processing']);

        $this->changeStatuses(['pending', 'processing', 'completed', 'cancelled']);
    }

    /** @param list<string> $statuses */
    private function changeStatuses(array $statuses): void
    {
        $change = function () use ($statuses): void {
            Schema::table('orders', function (Blueprint $table) use ($statuses): void {
                $table->enum('order_status', $statuses)->default('pending')->change();
            });
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(fn () => DB::transaction($change));

            return;
        }

        $change();
    }
};
