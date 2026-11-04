<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardChartsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_chart_includes_completed_orders_and_zero_fills_missing_months(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = $this->createUser('admin');
        $this->createOrder($admin, '2026-06-01', 100);
        $this->createOrder($admin, '2026-11-14', 250);
        $this->createOrder($admin, '2026-05-31', 900);
        $this->createOrder($admin, '2026-11-16', 900);
        $this->createOrder($admin, '2026-10-10', 500, 'processing');
        $this->createOrder($admin, '2026-09-10', 700, 'cancelled');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee('Completed sales trend')->assertSee('Pay Later repayments');
        $response->assertViewHas('salesChart', fn (array $chart) => array_column($chart, 'total_cents') === [10000, 0, 0, 0, 0, 25000]);
        $response->assertViewHas('salesChart', fn (array $chart) => array_column($chart, 'label') === ['Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026']);
    }

    public function test_sales_chart_handles_the_year_boundary(): void
    {
        $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
        $admin = $this->createUser('admin');
        $this->createOrder($admin, '2026-08-01', 100);
        $this->createOrder($admin, '2026-12-31', 200);
        $this->createOrder($admin, '2027-01-01', 300);
        $this->createOrder($admin, '2026-07-31', 999);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertViewHas('salesChart', fn (array $chart) => array_column($chart, 'total_cents') === [10000, 0, 0, 0, 20000, 30000]);
        $response->assertViewHas('salesChart', fn (array $chart) => $chart[0]['label'] === 'Aug 2026' && $chart[5]['label'] === 'Jan 2027');
    }

    public function test_repayments_split_partial_payments_current_balances_and_overdue_balances(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $order = $this->createOrder($customer, '2026-10-01', 700, 'pending', 'paylater');
        foreach ([
            [1, '2026-11-01', 100, 100],
            [2, '2026-11-14', 100, 25],
            [3, '2026-11-15', 200, 50],
            [4, '2026-12-15', 300, 0],
        ] as [$number, $date, $due, $paid]) {
            $installment = $order->installments()->create(['installment_number' => $number, 'due_date' => $date, 'amount_due' => $due]);
            if ($paid > 0) {
                $installment->payments()->create(['order_id' => $order->id, 'amount' => $paid, 'paid_at' => now()]);
            }
        }
        $cancelled = $this->createOrder($customer, '2026-10-01', 1000, 'cancelled', 'paylater');
        $cancelled->installments()->create(['installment_number' => 1, 'due_date' => '2026-11-01', 'amount_due' => 1000]);
        $cod = $this->createOrder($customer, '2026-10-01', 1000);
        $cod->installments()->create(['installment_number' => 1, 'due_date' => '2026-11-01', 'amount_due' => 1000]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertViewHas('creditChart', ['paid' => 17500, 'current' => 45000, 'overdue' => 7500]);
        $response->assertSee('175.00')->assertSee('450.00')->assertSee('75.00');
    }

    public function test_empty_dashboard_shows_meaningful_chart_empty_states(): void
    {
        $this->freezeTime();
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee('No completed orders in this period.')->assertSee('No Pay Later installments yet.');
        $response->assertViewHas('salesChart', fn (array $chart) => count($chart) === 6 && array_sum(array_column($chart, 'total_cents')) === 0);
    }

    public function test_overpayments_cannot_create_negative_remaining_chart_balances(): void
    {
        $this->freezeTime();
        $admin = $this->createUser('admin');
        $order = $this->createOrder($this->createUser(), today()->toDateString(), 100, 'pending', 'paylater');
        $installment = $order->installments()->create(['installment_number' => 1, 'due_date' => today(), 'amount_due' => 100]);
        $installment->payments()->create(['order_id' => $order->id, 'amount' => 150, 'paid_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertViewHas('creditChart', ['paid' => 10000, 'current' => 0, 'overdue' => 0]);
    }

    public function test_customer_cannot_view_admin_graphs(): void
    {
        $customer = $this->createUser();

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    private function createUser(string $role = 'customer'): User
    {
        $user = User::factory()->make(['username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'), 'role' => $role]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }

    private function createOrder(User $user, string $date, float $amount, string $status = 'completed', string $method = 'cod'): Order
    {
        return $user->orders()->create(['total_amount' => $amount, 'order_status' => $status, 'order_date' => $date, 'payment_method' => $method]);
    }
}
