<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderDeliveryStatusTest extends TestCase
{
    use DatabaseMigrations;

    public function test_admin_can_dispatch_and_complete_a_processing_order_without_changing_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(User::factory()->create(), 'processing');
        $product = Product::create(['name' => 'Delivery test item', 'price' => 100, 'stock_quantity' => 4]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Out for delivery');
        $this->patch(route('admin.orders.update', $order), ['order_status' => 'out_for_delivery'])->assertSessionHasNoErrors();

        $this->assertSame('out_for_delivery', $order->fresh()->order_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Out for delivery');
        $this->patch(route('admin.orders.update', $order), ['order_status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->order_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_admin_and_customer_can_filter_orders_out_for_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $order = $this->order($customer, 'out_for_delivery');
        $this->order($customer, 'processing');
        $this->order(User::factory()->create(), 'out_for_delivery');

        $this->actingAs($admin)->get(route('admin.orders', ['status' => 'out_for_delivery']))->assertOk()
            ->assertSee('Out for delivery')
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 2 && $orders->every(fn ($order) => $order->order_status === 'out_for_delivery'));
        $this->actingAs($customer)->get(route('customer.accountOverview', ['status' => 'out_for_delivery']))->assertOk()
            ->assertSee('Out for delivery')
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$order->id]);
    }

    public function test_customer_timeline_highlights_delivery_and_retains_pay_later_balance(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 'out_for_delivery');
        $order->update(['payment_method' => 'paylater']);
        $installment = $order->installments()->create(['installment_number' => 1, 'amount_due' => 100, 'due_date' => today()]);
        $installment->payments()->create(['order_id' => $order->id, 'amount' => 25, 'paid_at' => now()]);

        $this->actingAs($customer)->get(route('customer.orders.show', $order))->assertOk()
            ->assertSee('Your order is out for delivery')->assertSee('on its way to you.')
            ->assertViewHas('progressStep', 2)->assertViewHas('remainingBalance', 75.0)
            ->assertViewHas('canCancel', false);

        $this->assertSame(75.0, $order->fresh()->balance);
        $this->assertDatabaseCount('credit_payments', 1);
    }

    #[DataProvider('invalidTransitions')]
    public function test_delivery_transitions_cannot_skip_preparation_reopen_or_cancel_dispatched_orders(string $from, string $to): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(User::factory()->create(), $from);

        $this->actingAs($admin)->patchJson(route('admin.orders.update', $order), ['order_status' => $to])
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame($from, $order->fresh()->order_status);
    }

    public static function invalidTransitions(): array
    {
        return [
            'pending cannot dispatch' => ['pending', 'out_for_delivery'],
            'cannot return to processing' => ['out_for_delivery', 'processing'],
            'cannot cancel dispatched' => ['out_for_delivery', 'cancelled'],
            'cannot redispatch completed' => ['completed', 'out_for_delivery'],
            'cannot dispatch cancelled' => ['cancelled', 'out_for_delivery'],
        ];
    }

    public function test_customer_cannot_mark_an_order_out_for_delivery(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 'processing');

        $this->actingAs($customer)->patchJson(route('admin.orders.update', $order), ['order_status' => 'out_for_delivery'])->assertForbidden();

        $this->assertSame('processing', $order->fresh()->order_status);
    }

    public function test_schema_upgrade_and_rollback_preserve_orders_items_installments_and_payments(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 'processing');
        $item = $order->items()->create(['product_id' => null, 'quantity' => 1, 'price' => 100]);
        $installment = $order->installments()->create(['installment_number' => 1, 'amount_due' => 100, 'due_date' => today()]);
        $payment = $installment->payments()->create(['order_id' => $order->id, 'amount' => 25, 'paid_at' => now()]);
        $migration = require database_path('migrations/2026_11_09_033239_add_out_for_delivery_to_order_status.php');

        $migration->down();
        $migration->up();
        $order->refresh()->update(['order_status' => 'out_for_delivery']);
        $this->assertSame('out_for_delivery', $order->fresh()->order_status);
        $migration->down();

        $this->assertSame('processing', $order->fresh()->order_status);
        $this->assertModelExists($item);
        $this->assertModelExists($installment);
        $this->assertModelExists($payment);
        $this->assertSame($customer->id, $order->fresh()->user_id);
        $migration->up();
    }

    private function order(User $customer, string $status): Order
    {
        return $customer->orders()->create([
            'order_status' => $status, 'payment_method' => 'cod', 'total_amount' => 100, 'order_date' => now(),
        ]);
    }
}
