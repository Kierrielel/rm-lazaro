<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountOverviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_overview_paginates_only_the_authenticated_customers_orders(): void
    {
        $this->freezeTime();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $ids = [];
        foreach (range(1, 12) as $number) {
            $ids[] = $this->order($customer)->id;
        }
        $foreign = $this->order($other);

        $this->actingAs($customer)->get(route('customer.accountOverview', ['user_id' => $other->id]))
            ->assertOk()->assertSee('My Orders')
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 12
                && $orders->perPage() === 10
                && $orders->pluck('id')->all() === array_slice(array_reverse($ids), 0, 10)
                && ! $orders->contains('id', $foreign->id));
        $this->get(route('customer.accountOverview', ['page' => 2]))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$ids[1], $ids[0]]);
    }

    public function test_status_and_payment_filters_combine_and_remain_in_pagination_links(): void
    {
        $this->freezeTime();
        $customer = User::factory()->create();
        foreach (range(1, 11) as $number) {
            $this->order($customer, ['payment_method' => 'paylater', 'order_status' => 'processing']);
        }
        $this->order($customer, ['payment_method' => 'cod', 'order_status' => 'processing']);
        $this->order($customer, ['payment_method' => 'paylater', 'order_status' => 'pending']);
        $this->order(User::factory()->create(), ['payment_method' => 'paylater', 'order_status' => 'processing']);

        $response = $this->actingAs($customer)->get(route('customer.accountOverview', [
            'status' => 'processing', 'payment_method' => 'paylater', 'sort' => 'oldest',
        ]));

        $response->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 11
            && $orders->every(fn ($order) => $order->user_id === $customer->id
                && $order->payment_method === 'paylater' && $order->order_status === 'processing'));
        $url = $response->viewData('orders')->nextPageUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        $this->assertSame('processing', $parameters['status']);
        $this->assertSame('paylater', $parameters['payment_method']);
        $this->assertSame('oldest', $parameters['sort']);
        $this->assertSame('2', $parameters['page']);
    }

    #[DataProvider('sorts')]
    public function test_orders_follow_the_selected_sort(string $sort, array $expected): void
    {
        $this->freezeTime();
        $customer = User::factory()->create();
        $orders = [
            $this->order($customer, ['total_amount' => 300, 'order_date' => now()->subDays(2)]),
            $this->order($customer, ['total_amount' => 100, 'order_date' => now()->subDay()]),
            $this->order($customer, ['total_amount' => 200, 'order_date' => now()]),
        ];
        $ids = array_map(fn ($index) => $orders[$index]->id, $expected);

        $this->actingAs($customer)->get(route('customer.accountOverview', ['sort' => $sort]))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === $ids);
    }

    public static function sorts(): array
    {
        return [
            'newest' => ['newest', [2, 1, 0]],
            'oldest' => ['oldest', [0, 1, 2]],
            'highest total' => ['total_desc', [0, 2, 1]],
            'lowest total' => ['total_asc', [1, 2, 0]],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_and_sort_columns_are_rejected(string $field, mixed $value): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson(route('customer.accountOverview', [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'unknown status' => ['status', 'refunded'],
            'unknown payment' => ['payment_method', 'free'],
            'sort injection' => ['sort', 'total_amount desc; DROP TABLE orders'],
            'array input' => ['status', ['pending']],
            'negative page' => ['page', -1],
        ];
    }

    public function test_guest_must_log_in_to_view_orders(): void
    {
        $this->get(route('customer.accountOverview'))->assertRedirect(route('login'));
    }

    public function test_overview_displays_products_and_handles_deleted_products(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $product = Product::create(['name' => '<script>alert(1)</script>', 'price' => 100, 'stock_quantity' => 5]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 100]);
        $order->items()->create(['product_id' => null, 'variant_name' => 'Former option', 'quantity' => 1, 'price' => 100]);

        $this->actingAs($customer)->get(route('customer.accountOverview'))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Quantity: 2')->assertSee('Product no longer available')->assertSee('Former option');
    }

    public function test_customer_with_no_orders_sees_an_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get(route('customer.accountOverview'))->assertOk()
            ->assertSee('No orders found.')->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
    }

    private function order(User $customer, array $attributes = []): Order
    {
        return $customer->orders()->create([
            'total_amount' => 100, 'payment_method' => 'cod', 'order_status' => 'pending', 'order_date' => now(),
            ...$attributes,
        ]);
    }
}
