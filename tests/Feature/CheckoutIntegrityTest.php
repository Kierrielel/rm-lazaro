<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_two_customers_cannot_buy_the_same_remaining_stock(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $product = $this->product(1);
        $this->cart($first, $product);
        $secondCart = $this->cart($second, $product);

        $this->actingAs($first)->post(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertSessionHasNoErrors()->assertRedirect(route('cart.index'));
        $this->actingAs($second)->postJson(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertModelExists($secondCart);
    }

    public function test_a_second_submission_of_the_consumed_cart_does_not_duplicate_the_order(): void
    {
        $user = User::factory()->create();
        $product = $this->product(5);
        $this->cart($user, $product, 2);

        $this->actingAs($user)->post(route('checkout.store'), ['payment_method' => 'cod'])->assertSessionHasNoErrors();
        $this->post(route('checkout.store'), ['payment_method' => 'cod'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_late_stock_failure_rolls_back_the_entire_order_and_all_stock_changes(): void
    {
        $user = User::factory()->create();
        $first = $this->product(5);
        $second = $this->product(0);
        $this->cart($user, $first, 2);
        $this->cart($user, $second);

        $this->actingAs($user)->postJson(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertSame(5, $first->fresh()->stock_quantity);
        $this->assertSame(0, $second->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('cart', 2);
    }

    public function test_duplicate_cart_lines_cannot_oversell_combined_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->product(3);
        $this->cart($user, $product, 2);
        $this->cart($user, $product, 2);

        $this->actingAs($user)->postJson(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart', 2);
    }

    public function test_variant_checkout_uses_current_variant_price_and_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->product(100);
        $variant = $product->variants()->create(['name' => 'Large', 'options' => ['size' => 'Large'], 'price' => '19.99', 'stock_quantity' => 2]);
        $user->cart()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($user)->post(route('checkout.store'), ['payment_method' => 'cod'])->assertSessionHasNoErrors();

        $this->assertSame(0, $variant->fresh()->stock_quantity);
        $this->assertSame(100, $product->fresh()->stock_quantity);
        $this->assertSame('39.98', Order::firstOrFail()->total_amount);
        $this->assertDatabaseHas('order_items', ['variant_id' => $variant->id, 'variant_name' => 'Large', 'quantity' => 2, 'price' => 19.99]);
    }

    public function test_cart_cannot_use_another_products_variant(): void
    {
        $user = User::factory()->create();
        $product = $this->product(10);
        $other = $this->product(10);
        $variant = $other->variants()->create(['name' => 'Other', 'stock_quantity' => 10]);
        $user->cart()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user)->postJson(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertSame(10, $variant->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_product_that_now_requires_a_variant_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = $this->product(10);
        $this->cart($user, $product);
        $product->variants()->create(['name' => 'New option', 'stock_quantity' => 10]);

        $this->actingAs($user)->postJson(route('checkout.store'), ['payment_method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    #[DataProvider('unavailableMethods')]
    public function test_unimplemented_cart_methods_cannot_create_orders(string $method): void
    {
        $user = User::factory()->create();
        $product = $this->product(10);
        $this->cart($user, $product);

        $this->actingAs($user)->postJson(route('checkout.store'), ['payment_method' => $method])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart', 1);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public static function unavailableMethods(): array
    {
        return ['qrph' => ['qrph'], 'gcash' => ['gcash'], 'maya' => ['maya']];
    }

    #[DataProvider('schedules')]
    public function test_installments_use_the_selected_due_day_and_exact_cent_totals(string $date, string $dueDay, array $dates): void
    {
        $this->travelTo(Carbon::parse($date));
        $user = User::factory()->create(['credit_approved' => true, 'credit_limit' => 1000, 'credit_due_day' => $dueDay]);
        $product = $this->product(2, '229.50');
        $this->cart($user, $product);

        $this->actingAs($user)->post(route('checkout.store'), [
            'payment_method' => 'paylater', 'credit_term_months' => 3, 'confirm_credit_plan' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('cart.index'));

        $order = Order::firstOrFail();
        $this->assertSame('229.50', $order->total_amount);
        $this->assertSame('24.10', $order->credit_finance_charge);
        $this->assertSame($dates[0], $order->due_date->toDateString());
        $this->assertSame($dates, $order->installments->map(fn ($row) => $row->due_date->toDateString())->all());
        $this->assertSame(['84.54', '84.53', '84.53'], $order->installments->pluck('amount_due')->all());
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public static function schedules(): array
    {
        return [
            'before fifteenth' => ['2026-01-14 12:00', '15', ['2026-01-15', '2026-02-15', '2026-03-15']],
            'on fifteenth' => ['2026-01-15 12:00', '15', ['2026-02-15', '2026-03-15', '2026-04-15']],
            'before month end' => ['2026-01-30 12:00', 'last_day', ['2026-01-31', '2026-02-28', '2026-03-31']],
            'on month end' => ['2026-01-31 12:00', 'last_day', ['2026-02-28', '2026-03-31', '2026-04-30']],
            'leap year' => ['2028-01-31 12:00', 'last_day', ['2028-02-29', '2028-03-31', '2028-04-30']],
            'year rollover' => ['2026-12-31 12:00', 'last_day', ['2027-01-31', '2027-02-28', '2027-03-31']],
        ];
    }

    public function test_denied_credit_preserves_a_new_overdue_hold_without_consuming_stock(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['credit_approved' => true, 'credit_limit' => 1000, 'credit_due_day' => '15']);
        $debt = $user->orders()->create(['payment_method' => 'paylater', 'total_amount' => 100, 'order_status' => 'pending', 'order_date' => now()->subMonth()]);
        $debt->installments()->create(['installment_number' => 1, 'amount_due' => 100, 'due_date' => today()->subDay()]);
        $product = $this->product(2);
        $this->cart($user, $product);

        $this->actingAs($user)->postJson(route('checkout.store'), [
            'payment_method' => 'paylater', 'credit_term_months' => 3, 'confirm_credit_plan' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->assertTrue($user->fresh()->credit_on_hold);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart', 1);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_checkout_ignores_client_amount_user_and_price(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->product(5, '12.34');
        $this->cart($user, $product, 3);
        $otherCart = $this->cart($other, $product);

        $this->actingAs($user)->post(route('checkout.store'), [
            'payment_method' => 'cod', 'user_id' => $other->id, 'total_amount' => 0, 'price' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total_amount' => 37.02]);
        $this->assertModelExists($otherCart);
    }

    public function test_checkout_is_throttled_per_customer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('checkout.store'), [])->assertUnprocessable();
        }

        $this->postJson(route('checkout.store'), [])->assertTooManyRequests();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->post(route('checkout.store'), ['payment_method' => 'cod'])->assertRedirect(route('login'));
    }

    public function test_cart_changes_and_checkout_preserve_quantities_and_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->product(10);
        $otherCart = $this->cart($other, $product);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $cart = $user->cart()->firstOrFail();
        $this->patch(route('cart.update', $cart), ['action' => 'increase'])->assertSessionHasNoErrors();
        $this->patchJson(route('cart.update', $otherCart), ['action' => 'increase'])->assertForbidden();
        $this->deleteJson(route('cart.remove', $otherCart))->assertForbidden();
        $this->post(route('checkout.store'), ['payment_method' => 'cod'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 4]);
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(1, $otherCart->fresh()->quantity);
        $this->assertDatabaseCount('cart', 1);
    }

    public function test_owned_cart_items_can_be_removed_or_decreased_to_zero(): void
    {
        $user = User::factory()->create();
        $first = $this->cart($user, $this->product(10));
        $second = $this->cart($user, $this->product(10));

        $this->actingAs($user)->delete(route('cart.remove', $first))->assertSessionHasNoErrors();
        $this->patch(route('cart.update', $second), ['action' => 'decrease'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('cart', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failed_pay_later_stock_reservation_rolls_back_installments(): void
    {
        $user = User::factory()->create(['credit_approved' => true, 'credit_limit' => 1000, 'credit_due_day' => '15']);
        $product = $this->product(0);
        $this->cart($user, $product);

        $this->actingAs($user)->postJson(route('checkout.store'), [
            'payment_method' => 'paylater', 'credit_term_months' => 3, 'confirm_credit_plan' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('stock');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('credit_installments', 0);
        $this->assertDatabaseCount('cart', 1);
    }

    public function test_credit_limit_includes_the_finance_charge(): void
    {
        $user = User::factory()->create(['credit_approved' => true, 'credit_limit' => 100, 'credit_due_day' => '15']);
        $product = $this->product(5, '100.00');
        $this->cart($user, $product);

        $this->actingAs($user)->postJson(route('checkout.store'), [
            'payment_method' => 'paylater', 'credit_term_months' => 3, 'confirm_credit_plan' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cart_disables_unsupported_methods_and_supplies_saved_due_day_to_preview(): void
    {
        $user = User::factory()->create(['credit_approved' => true, 'credit_due_day' => 'last_day']);
        $this->cart($user, $this->product(10));

        $this->actingAs($user)->get(route('cart.index'))->assertOk()
            ->assertSee('id="qrph" name="payment_method" value="qrph" disabled', false)
            ->assertSee('data-due-day="last_day"', false);
    }

    private function product(int $stock, string $price = '100.00'): Product
    {
        return Product::create(['name' => fake()->unique()->word(), 'price' => $price, 'stock_quantity' => $stock]);
    }

    private function cart(User $user, Product $product, int $quantity = 1): Cart
    {
        return $user->cart()->create(['product_id' => $product->id, 'quantity' => $quantity]);
    }
}
