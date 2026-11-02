<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrderSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_finds_a_specific_order_number(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $customer = $this->createUser();
        $match = $this->createOrder($customer);
        $this->createOrder($customer);

        $response = $this->actingAs($admin)->get(route('admin.orders', ['search' => '#'.$match->id]));

        $response->assertOk()->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$match->id]);
    }

    #[DataProvider('customerSearchFields')]
    public function test_search_matches_customer_details(string $field, string $value, string $search): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $match = $this->createOrder($this->createUser([$field => $value]));
        $this->createOrder($this->createUser());

        $response = $this->actingAs($admin)->get(route('admin.orders', ['search' => $search]));

        $response->assertOk()->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$match->id]);
    }

    public static function customerSearchFields(): array
    {
        return [
            'username' => ['username', 'hardware_customer', 'hardware_customer'],
            'full name' => ['name', 'Maria Hardware', 'Maria Hard'],
            'email' => ['email', 'orders@example.test', 'orders@example.test'],
            'phone' => ['phone_no', '09123456789', '09123456789'],
        ];
    }

    public function test_search_is_grouped_with_status_filter(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $customer = $this->createUser(['username' => 'matching_customer']);
        $pending = $this->createOrder($customer);
        $this->createOrder($customer, ['order_status' => 'cancelled']);
        $this->createOrder($this->createUser());

        $response = $this->actingAs($admin)->get(route('admin.orders', ['search' => 'matching_customer', 'status' => 'pending']));

        $response->assertOk()->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$pending->id]);
    }

    #[DataProvider('sortingOptions')]
    public function test_orders_use_the_selected_sort(string $sort, array $expectedKeys): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $alice = $this->createUser(['username' => 'alice']);
        $zoe = $this->createUser(['username' => 'zoe']);
        $orders = [
            'a' => $this->createOrder($alice, ['order_date' => '2026-11-03', 'total_amount' => 200]),
            'b' => $this->createOrder($zoe, ['order_date' => '2026-11-01', 'total_amount' => 10]),
            'c' => $this->createOrder($alice, ['order_date' => '2026-11-02', 'total_amount' => 100]),
        ];
        $expected = array_map(fn (string $key) => $orders[$key]->id, $expectedKeys);

        $response = $this->actingAs($admin)->get(route('admin.orders', ['sort' => $sort]));

        $response->assertOk()->assertViewHas('orders', fn ($page) => $page->pluck('id')->all() === $expected);
    }

    public static function sortingOptions(): array
    {
        return [
            'newest' => ['newest', ['a', 'c', 'b']],
            'oldest' => ['oldest', ['b', 'c', 'a']],
            'highest total' => ['total_desc', ['a', 'c', 'b']],
            'lowest total' => ['total_asc', ['b', 'c', 'a']],
            'customer ascending' => ['customer_asc', ['c', 'a', 'b']],
            'customer descending' => ['customer_desc', ['b', 'c', 'a']],
        ];
    }

    public function test_pagination_retains_filters_and_uses_stable_order_for_identical_dates(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $customer = $this->createUser(['username' => 'paged_customer']);
        $ids = [];
        foreach (range(1, 17) as $number) {
            $ids[] = $this->createOrder($customer, ['order_date' => '2026-11-01'])->id;
        }
        $this->createOrder($customer, ['order_status' => 'completed']);
        $filters = ['search' => 'paged_customer', 'status' => 'pending', 'sort' => 'newest'];

        $response = $this->actingAs($admin)->get(route('admin.orders', $filters));

        $response->assertOk()->assertViewHas('orders', function ($page) use ($filters) {
            parse_str(parse_url($page->nextPageUrl(), PHP_URL_QUERY), $query);

            return $page->total() === 17 && $query['search'] === $filters['search']
                && $query['status'] === 'pending' && $query['sort'] === 'newest';
        });
        $this->get(route('admin.orders', [...$filters, 'page' => 2]))->assertOk()
            ->assertViewHas('orders', fn ($page) => $page->pluck('id')->all() === [$ids[1], $ids[0]]);
    }

    public function test_archived_customers_order_history_remains_searchable(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $customer = $this->createUser(['username' => 'archived_customer']);
        $customer->archived_at = now();
        $customer->save();
        $order = $this->createOrder($customer);

        $response = $this->actingAs($admin)->get(route('admin.orders', ['search' => 'archived_customer', 'sort' => 'customer_asc']));

        $response->assertOk()->assertSee('archived_customer')
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$order->id]);
    }

    public function test_customer_sort_keeps_orders_whose_customer_record_is_unavailable(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $order = Order::create(['user_id' => null, 'total_amount' => 50, 'order_status' => 'pending', 'order_date' => now(), 'payment_method' => 'cod']);

        $response = $this->actingAs($admin)->get(route('admin.orders', ['sort' => 'customer_asc']));

        $response->assertOk()->assertSee('Deleted user')
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$order->id]);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filter_input_is_rejected(string $field, mixed $value): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        $this->actingAs($admin)->getJson(route('admin.orders', [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'invalid sort' => ['sort', 'total_amount; DROP TABLE orders'],
            'array sort' => ['sort', ['newest']],
            'invalid status' => ['status', 'unrecognized'],
            'array search' => ['search', ['customer']],
            'long search' => ['search', str_repeat('a', 101)],
            'invalid page' => ['page', -1],
        ];
    }

    public function test_search_terms_are_bound_and_displayed_as_escaped_text(): void
    {
        $admin = $this->createUser(['role' => 'admin']);
        $this->createOrder($this->createUser());
        $search = '\"><script>alert(1)</script> OR 1=1 --';

        $response = $this->actingAs($admin)->get(route('admin.orders', ['search' => $search]));

        $response->assertOk()->assertSee('No orders match your search or filters.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_customer_cannot_search_admin_orders(): void
    {
        $this->actingAs($this->createUser())->get(route('admin.orders', ['search' => 'customer']))->assertForbidden();
    }

    public function test_guest_cannot_search_admin_orders(): void
    {
        $this->get(route('admin.orders', ['search' => 'customer']))->assertRedirect(route('login'));
    }

    private function createUser(array $attributes = []): User
    {
        $user = User::factory()->make([
            'username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'),
            ...$attributes,
        ]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }

    private function createOrder(User $user, array $attributes = []): Order
    {
        return $user->orders()->create([
            'total_amount' => 100, 'order_status' => 'pending', 'order_date' => now(), 'payment_method' => 'cod',
            ...$attributes,
        ]);
    }
}
