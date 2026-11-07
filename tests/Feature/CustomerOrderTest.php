<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\OrderStatusUpdater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('orderStatuses')]
    public function test_owner_can_view_order_details_for_each_status(string $status, string $heading, bool $canCancel): void
    {
        $this->freezeTime();
        $customer = User::factory()->create(['name' => 'Juan Customer', 'province' => 'Bulacan', 'city' => 'San Miguel', 'barangay' => 'Test barangay', 'unit' => '123']);
        $order = $this->order($customer, ['order_status' => $status]);
        $product = Product::create(['name' => 'Demo hardware item', 'price' => 50, 'stock_quantity' => 8]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 50]);

        $response = $this->actingAs($customer)->get(route('customer.orders.show', $order));

        $response->assertOk()->assertSee($heading)->assertSee('Demo hardware item')
            ->assertSee('Quantity: 2')->assertSee('Juan Customer')->assertSee('Bulacan')
            ->assertSee('Payment summary')->assertViewHas('canCancel', $canCancel);
        $this->assertSame($status, $order->fresh()->order_status);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public static function orderStatuses(): array
    {
        return [
            'pending' => ['pending', 'Waiting for confirmation', true],
            'processing' => ['processing', 'Your order is being processed', false],
            'completed' => ['completed', 'Order completed', false],
            'cancelled' => ['cancelled', 'Order cancelled', false],
        ];
    }

    public function test_customer_cancels_pending_order_and_stock_is_restored_once(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $product = Product::create(['name' => 'Reserved product', 'price' => 50, 'stock_quantity' => 8]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 50]);

        $this->actingAs($customer)->patch(route('customer.orders.cancel', $order))
            ->assertRedirect(route('customer.orders.show', $order))->assertSessionHas('success');
        $this->patchJson(route('customer.orders.cancel', $order))
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_cancelling_a_variant_order_restores_only_the_variant(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $product = Product::create(['name' => 'Variant product', 'price' => 50, 'stock_quantity' => 100]);
        $variant = $product->variants()->create(['name' => 'Large', 'stock_quantity' => 3]);
        $order->items()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'variant_name' => 'Large', 'quantity' => 2, 'price' => 50]);

        $this->actingAs($customer)->patch(route('customer.orders.cancel', $order))->assertSessionHasNoErrors();

        $this->assertSame(5, $variant->fresh()->stock_quantity);
        $this->assertSame(100, $product->fresh()->stock_quantity);
    }

    #[DataProvider('nonPendingStatuses')]
    public function test_customer_cannot_cancel_non_pending_orders(string $status): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, ['order_status' => $status]);

        $this->actingAs($customer)->patchJson(route('customer.orders.cancel', $order))
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame($status, $order->fresh()->order_status);
    }

    public static function nonPendingStatuses(): array
    {
        return ['processing' => ['processing'], 'completed' => ['completed'], 'cancelled' => ['cancelled']];
    }

    public function test_order_updated_after_the_customer_opened_the_page_cannot_be_cancelled(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $this->actingAs($customer)->get(route('customer.orders.show', $order))->assertOk()->assertViewHas('canCancel', true);
        Order::whereKey($order->id)->update(['order_status' => 'processing']);

        $this->patchJson(route('customer.orders.cancel', $order))
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame('processing', $order->fresh()->order_status);
    }

    public function test_cancellation_service_rechecks_a_stale_order_under_its_transaction(): void
    {
        $order = $this->order(User::factory()->create());
        Order::whereKey($order->id)->update(['order_status' => 'processing']);

        try {
            app(OrderStatusUpdater::class)->update($order, 'cancelled', requiredCurrentStatus: 'pending');
            $this->fail('A stale pending model must not cancel a processing order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order_status', $exception->errors());
        }

        $this->assertSame('processing', $order->fresh()->order_status);
    }

    #[DataProvider('customerActions')]
    public function test_customer_cannot_view_or_cancel_another_customers_order(string $method, string $route): void
    {
        $customer = User::factory()->create();
        $order = $this->order(User::factory()->create());

        $this->actingAs($customer)->{$method}(route($route, $order))->assertNotFound();

        $this->assertSame('pending', $order->fresh()->order_status);
    }

    #[DataProvider('customerActions')]
    public function test_guests_must_log_in_to_view_or_cancel_orders(string $method, string $route): void
    {
        $order = $this->order(User::factory()->create());

        $this->{$method}(route($route, $order))->assertRedirect(route('login'));

        $this->assertSame('pending', $order->fresh()->order_status);
    }

    public static function customerActions(): array
    {
        return ['show' => ['get', 'customer.orders.show'], 'cancel' => ['patch', 'customer.orders.cancel']];
    }

    #[DataProvider('paymentAttemptStatuses')]
    public function test_unresolved_payments_block_customer_cancellation(string $status): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, ['payment_method' => 'paylater']);
        $installment = $order->installments()->create(['installment_number' => 1, 'amount_due' => 100, 'due_date' => today()]);
        $attempt = $customer->creditPaymentAttempts()->create([
            'reference' => (string) Str::uuid(), 'method' => 'qrph', 'amount_cents' => 10000, 'currency' => 'PHP', 'status' => $status,
        ]);
        $attempt->allocations()->create(['credit_installment_id' => $installment->id, 'amount_cents' => 10000]);

        $this->actingAs($customer)->get(route('customer.orders.show', $order))->assertOk()
            ->assertViewHas('canCancel', false)->assertSee('Please contact the store before cancelling.');
        $this->patchJson(route('customer.orders.cancel', $order))->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame($status, $attempt->fresh()->status);
        $this->assertDatabaseCount('credit_payments', 0);
    }

    public static function paymentAttemptStatuses(): array
    {
        return ['creating' => ['creating'], 'pending' => ['pending'], 'review' => ['review']];
    }

    public function test_recorded_payments_block_customer_cancellation(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, ['payment_method' => 'paylater']);
        $order->payments()->create(['amount' => 10, 'paid_at' => now()]);

        $this->actingAs($customer)->patchJson(route('customer.orders.cancel', $order))
            ->assertUnprocessable()->assertJsonValidationErrors('order_status');

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertDatabaseCount('credit_payments', 1);
    }

    public function test_cancelled_unpaid_pay_later_order_releases_credit_and_retains_its_schedule(): void
    {
        $customer = User::factory()->create(['credit_approved' => true, 'credit_limit' => 1000]);
        $order = $this->order($customer, ['payment_method' => 'paylater', 'credit_finance_charge' => 10.50]);
        $installment = $order->installments()->create(['installment_number' => 1, 'amount_due' => 110.50, 'due_date' => today()]);
        $this->assertSame(110.5, $customer->unpaidCreditBalance());

        $this->actingAs($customer)->patch(route('customer.orders.cancel', $order))->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $customer->fresh()->unpaidCreditBalance());
        $this->assertModelExists($installment);
        $this->get(route('customer.orders.show', $order))->assertOk()->assertSee('excluded from your active Pay Later bills.');
    }

    public function test_pay_later_details_display_installment_payments_and_balances(): void
    {
        $this->freezeTime();
        $customer = User::factory()->create();
        $order = $this->order($customer, [
            'payment_method' => 'paylater', 'credit_term_months' => 3, 'credit_interest_rate' => 3.5, 'credit_finance_charge' => 10.50,
        ]);
        foreach (range(1, 3) as $number) {
            $installment = $order->installments()->create(['installment_number' => $number, 'amount_due' => $number === 3 ? 36.84 : 36.83, 'due_date' => today()->addMonthsNoOverflow($number - 1)]);
            if ($number === 1) {
                $installment->payments()->create(['order_id' => $order->id, 'amount' => 36.83, 'paid_at' => now()]);
            }
        }

        $this->actingAs($customer)->get(route('customer.orders.show', $order))->assertOk()
            ->assertSee('Installments paid: 1/3')->assertSee('36.83')->assertSee('73.67')
            ->assertSee('Finance charge')->assertSee('Manage Pay Later payments')
            ->assertViewHas('remainingBalance', 73.67)->assertViewHas('canCancel', false);
    }

    public function test_deleted_products_and_untrusted_product_names_render_safely(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $product = Product::create(['name' => '<script>alert(1)</script>', 'price' => 100, 'stock_quantity' => 1]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        $order->items()->create(['product_id' => null, 'quantity' => 1, 'price' => 0]);

        $this->actingAs($customer)->get(route('customer.orders.show', $order))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Product no longer available');
    }

    public function test_my_orders_links_to_the_owned_order_details(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);

        $this->actingAs($customer)->get(route('customer.accountOverview'))->assertOk()
            ->assertSee(route('customer.orders.show', $order))->assertSee('View details');
    }

    public function test_cancel_endpoint_ignores_forged_status_and_user_fields(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->order($customer);

        $this->actingAs($customer)->patch(route('customer.orders.cancel', $order), [
            'order_status' => 'completed', 'user_id' => $other->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame($customer->id, $order->fresh()->user_id);
    }

    private function order(User $customer, array $attributes = []): Order
    {
        return $customer->orders()->create([
            'order_status' => 'pending', 'payment_method' => 'cod', 'total_amount' => 100, 'order_date' => now(),
            ...$attributes,
        ]);
    }
}
