<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cancellation_restores_product_and_variant_stock_only_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $product = Product::create(['name' => 'Basic product', 'price' => 10, 'stock_quantity' => 3]);
        $variantProduct = Product::create(['name' => 'Variant product', 'price' => 20, 'stock_quantity' => 100]);
        $variant = $variantProduct->variants()->create(['name' => 'Large', 'stock_quantity' => 1]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 10]);
        $order->items()->create(['product_id' => $variantProduct->id, 'variant_id' => $variant->id, 'variant_name' => 'Large', 'quantity' => 3, 'price' => 20]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['order_status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(100, $variantProduct->fresh()->stock_quantity);
    }

    #[DataProvider('finalTransitions')]
    public function test_final_orders_cannot_be_reopened_or_cancelled(string $from, string $to): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order($from);

        $this->actingAs($admin)->patchJson(route('admin.orders.update', $order), ['order_status' => $to])
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame($from, $order->fresh()->order_status);
    }

    public static function finalTransitions(): array
    {
        return [
            'cancelled to pending' => ['cancelled', 'pending'],
            'cancelled to completed' => ['cancelled', 'completed'],
            'completed to cancelled' => ['completed', 'cancelled'],
        ];
    }

    #[DataProvider('openPaymentStatuses')]
    public function test_orders_with_unresolved_payments_cannot_be_cancelled(string $status): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $order->update(['payment_method' => 'paylater']);
        $installment = $order->installments()->create(['installment_number' => 1, 'due_date' => today(), 'amount_due' => 100]);
        $attempt = $order->user->creditPaymentAttempts()->create([
            'reference' => (string) Str::uuid(), 'status' => $status, 'method' => 'qrph',
            'amount_cents' => 10000, 'currency' => 'PHP',
        ]);
        $attempt->allocations()->create(['credit_installment_id' => $installment->id, 'amount_cents' => 10000]);

        $this->actingAs($admin)->patchJson(route('admin.orders.update', $order), ['order_status' => 'cancelled'])
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame($status, $attempt->fresh()->status);
    }

    public static function openPaymentStatuses(): array
    {
        return ['creating' => ['creating'], 'pending' => ['pending'], 'review' => ['review']];
    }

    public function test_recorded_payment_prevents_cancellation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $order->update(['payment_method' => 'paylater']);
        $order->payments()->create(['amount' => 10, 'paid_at' => now()]);

        $this->actingAs($admin)->patchJson(route('admin.orders.update', $order), ['order_status' => 'cancelled'])
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertDatabaseCount('credit_payments', 1);
    }

    public function test_deleted_variant_is_not_restocked_as_a_base_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $product = Product::create(['name' => 'Former variant product', 'price' => 10, 'stock_quantity' => 4]);
        $order->items()->create(['product_id' => $product->id, 'variant_id' => null, 'variant_name' => 'Deleted variant', 'quantity' => 2, 'price' => 10]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertSame('cancelled', $order->fresh()->order_status);
    }

    public function test_failed_payment_does_not_block_cancellation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $order->update(['payment_method' => 'paylater']);
        $installment = $order->installments()->create(['installment_number' => 1, 'due_date' => today(), 'amount_due' => 100]);
        $attempt = $order->user->creditPaymentAttempts()->create([
            'reference' => (string) Str::uuid(), 'status' => 'failed', 'method' => 'qrph', 'amount_cents' => 10000, 'currency' => 'PHP',
        ]);
        $attempt->allocations()->create(['credit_installment_id' => $installment->id, 'amount_cents' => 10000]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->order_status);
    }

    public function test_customer_cannot_change_order_status(): void
    {
        $order = $this->order();

        $this->actingAs($order->user)->patchJson(route('admin.orders.update', $order), ['order_status' => 'cancelled'])->assertForbidden();

        $this->assertSame('pending', $order->fresh()->order_status);
    }

    public function test_processing_and_completion_do_not_restock_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $product = Product::create(['name' => 'Reserved product', 'price' => 10, 'stock_quantity' => 4]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 10]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'processing'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $order), ['order_status' => 'completed'])->assertSessionHasNoErrors();

        $this->assertSame('completed', $order->fresh()->order_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    private function order(string $status = 'pending'): Order
    {
        return User::factory()->create()->orders()->create([
            'order_status' => $status, 'payment_method' => 'cod', 'total_amount' => 100, 'order_date' => now(),
        ]);
    }
}
