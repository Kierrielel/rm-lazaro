<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserArchiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_archiving_preserves_user_orders_installments_and_payment_records(): void
    {
        $this->freezeTime();
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $oldToken = $customer->remember_token;
        $order = $customer->orders()->create(['total_amount' => 100, 'order_status' => 'pending', 'payment_method' => 'paylater', 'order_date' => now()]);
        $installment = $order->installments()->create(['installment_number' => 1, 'due_date' => today(), 'amount_due' => 100]);
        $payment = $installment->payments()->create(['order_id' => $order->id, 'amount' => 25, 'paid_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.users.archive', $customer))->assertRedirect(route('admin.users'))->assertSessionHas('success');

        $this->assertNotNull($customer->fresh()->archived_at);
        $this->assertNotSame($oldToken, $customer->fresh()->remember_token);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'email' => $customer->email]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('credit_installments', ['id' => $installment->id]);
        $this->assertDatabaseHas('credit_payments', ['id' => $payment->id]);
        $this->assertSame($customer->id, $order->fresh()->user->id);
    }

    public function test_active_and_archived_filters_keep_accounts_separate_and_searchable(): void
    {
        $admin = $this->createUser('admin');
        $active = $this->createUser();
        $archived = $this->createUser();
        $archived->archived_at = now();
        $archived->save();

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee($active->username)->assertDontSee($archived->email);
        $this->get(route('admin.users', ['status' => 'archived', 'search' => $archived->email]))
            ->assertOk()->assertSee($archived->email)->assertSee('Restore')->assertDontSee($active->email);
        $this->get(route('admin.users', ['status' => 'all']))->assertOk()->assertSee($active->email)->assertSee($archived->email);
    }

    #[DataProvider('loginFields')]
    public function test_archived_user_cannot_log_in_with_any_identifier(string $field): void
    {
        $customer = $this->createUser();
        $customer->archived_at = now();
        $customer->save();

        $this->post('/login', ['login_id' => $customer->{$field}, 'password' => 'password'])->assertSessionHasErrors('login_id');

        $this->assertGuest();
    }

    public static function loginFields(): array
    {
        return ['username' => ['username'], 'email' => ['email'], 'phone' => ['phone_no']];
    }

    public function test_existing_login_session_is_logged_out_after_archiving(): void
    {
        $customer = $this->createUser();
        $this->actingAs($customer);
        User::whereKey($customer->id)->update(['archived_at' => now()]);

        $this->get(route('cart.index'))->assertRedirect(route('login'))->assertSessionHasErrors('login_id');

        $this->assertGuest();
    }

    public function test_existing_json_session_receives_401_after_archiving(): void
    {
        $customer = $this->createUser();
        $this->actingAs($customer);
        User::whereKey($customer->id)->update(['archived_at' => now()]);

        $this->getJson(route('cart.index'))->assertUnauthorized()->assertExactJson(['message' => 'This account has been archived.']);

        $this->assertGuest();
    }

    public function test_database_sessions_are_revoked_on_archive(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        DB::table('sessions')->insert(['id' => 'customer-session', 'user_id' => $customer->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($admin)->patch(route('admin.users.archive', $customer))->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('sessions', ['id' => 'customer-session']);
    }

    public function test_admin_can_restore_archived_user_and_user_can_log_in_again(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $customer->archived_at = now();
        $customer->save();

        $this->actingAs($admin)->patch(route('admin.users.restore', $customer))->assertRedirect(route('admin.users', ['status' => 'archived']));
        $this->assertNull($customer->fresh()->archived_at);
        $this->post('/logout');
        $this->post('/login', ['login_id' => $customer->email, 'password' => 'password'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_admin_accounts_cannot_be_archived(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)->patch(route('admin.users.archive', $admin))->assertForbidden();

        $this->assertNull($admin->fresh()->archived_at);
    }

    public function test_customer_cannot_archive_or_restore_accounts(): void
    {
        $customer = $this->createUser();
        $other = $this->createUser();

        $this->actingAs($customer)->patch(route('admin.users.archive', $other))->assertForbidden();
        $this->patch(route('admin.users.restore', $other))->assertForbidden();

        $this->assertNull($other->fresh()->archived_at);
    }

    public function test_guest_cannot_archive_accounts(): void
    {
        $customer = $this->createUser();

        $this->patch(route('admin.users.archive', $customer))->assertRedirect(route('login'));

        $this->assertNull($customer->fresh()->archived_at);
    }

    public function test_permanent_delete_route_is_not_available(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();

        $this->actingAs($admin)->delete('/admin/users/'.$customer->id)->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_archived_account_cannot_be_edited_until_restored(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $customer->archived_at = now();
        $customer->save();

        $this->actingAs($admin)->patch(route('admin.users.update', $customer), ['role' => 'admin'])->assertForbidden();

        $this->assertSame('customer', $customer->fresh()->role);
    }

    private function createUser(string $role = 'customer'): User
    {
        $user = User::factory()->make(['username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'), 'role' => $role]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }
}
