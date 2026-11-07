<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_can_view_profile_address_and_security_forms(): void
    {
        $customer = User::factory()->create(['province' => 'Bulacan', 'city' => 'Angat', 'barangay' => 'Banaban', 'unit' => '12 Test Street']);
        config(['services.paymongo.secret' => 'sk_test_private_settings']);

        $this->actingAs($customer)->get(route('customer.settings'))->assertOk()
            ->assertSee('Profile details')->assertSee('Billing address')->assertSee('Change password')
            ->assertSee('12 Test Street')->assertSee('Banaban')->assertSee($customer->email)
            ->assertSee(route('customer.settings.update'))->assertSee(route('customer.settings.address'))
            ->assertDontSee('sk_test_private_settings')->assertDontSee($customer->password);
    }

    public function test_profile_updates_only_the_authenticated_account_and_ignores_privileged_fields(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create(['role' => 'admin']);
        $otherEmail = $other->email;

        $this->actingAs($customer)->put(route('customer.settings.update'), [
            'name' => 'Juan Updated', 'username' => 'updated_customer', 'email' => 'updated@example.test',
            'phone_no' => '09123456780', 'current_password' => 'password',
            'id' => $other->id, 'user_id' => $other->id, 'role' => 'admin', 'credit_approved' => true, 'credit_limit' => 999999,
        ])->assertRedirect(route('customer.settings'))->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('Juan Updated', $customer->name);
        $this->assertSame('updated_customer', $customer->username);
        $this->assertSame('updated@example.test', $customer->email);
        $this->assertSame('customer', $customer->role);
        $this->assertFalse($customer->credit_approved);
        $this->assertNull($customer->credit_limit);
        $this->assertSame($otherEmail, $other->fresh()->email);
        $this->assertTrue(Hash::check('password', $customer->password));
    }

    public function test_profile_can_keep_its_current_unique_identifiers(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->put(route('customer.settings.update'), $this->profile($customer))
            ->assertSessionHasNoErrors()->assertRedirect(route('customer.settings'));

        $this->assertSame($customer->username, $customer->fresh()->username);
    }

    #[DataProvider('invalidProfileFields')]
    public function test_invalid_profile_updates_leave_the_account_unchanged(string $field, mixed $value): void
    {
        $customer = User::factory()->create();
        $email = $customer->email;
        $data = array_replace($this->profile($customer), [$field => $value]);

        $this->actingAs($customer)->putJson(route('customer.settings.update'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame($email, $customer->fresh()->email);
        $this->assertSame($customer->name, $customer->fresh()->name);
    }

    public static function invalidProfileFields(): array
    {
        return [
            'wrong password' => ['current_password', 'wrong'],
            'missing password' => ['current_password', ''],
            'missing name' => ['name', ''],
            'phone username' => ['username', '09123456789'],
            'email username' => ['username', 'user@example.test'],
            'invalid phone' => ['phone_no', '1234'],
            'invalid email' => ['email', 'not-an-email'],
        ];
    }

    public function test_profile_rejects_another_users_login_details(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $data = array_replace($this->profile($customer), ['username' => $other->username, 'email' => $other->email, 'phone_no' => $other->phone_no]);

        $this->actingAs($customer)->putJson(route('customer.settings.update'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors(['username', 'email', 'phone_no']);

        $this->assertSame($customer->email, $customer->fresh()->email);
    }

    public function test_billing_address_is_saved_and_displayed_in_the_account_and_cart(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create(['unit' => 'Other address']);

        $this->actingAs($customer)->put(route('customer.settings.address'), $this->address() + [
            'user_id' => $other->id, 'role' => 'admin', 'credit_due_day' => 'last_day',
        ])->assertRedirect(route('customer.settings').'#billing-address')->assertSessionHas('success');

        $this->assertSame('12 Test Street, Banaban, Angat, Bulacan', $customer->fresh()->full_address);
        $this->assertSame('Other address', $other->fresh()->unit);
        $this->assertSame('customer', $customer->fresh()->role);
        $this->assertNull($customer->fresh()->credit_due_day);
        $this->get(route('customer.accountOverview'))->assertOk()->assertSee('12 Test Street')->assertSee('Banaban');
    }

    #[DataProvider('invalidAddressFields')]
    public function test_billing_address_rejects_invalid_locations_and_mismatched_barangays(string $field, mixed $value): void
    {
        $customer = User::factory()->create(['unit' => 'Original address']);
        $data = array_replace($this->address(), [$field => $value]);

        $this->actingAs($customer)->putJson(route('customer.settings.address'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame('Original address', $customer->fresh()->unit);
    }

    public static function invalidAddressFields(): array
    {
        return [
            'outside province' => ['province', 'Manila'],
            'invalid city' => ['city', 'Unknown city'],
            'city from another province' => ['city', 'Quezon City'],
            'wrong barangay for city' => ['barangay', 'Calawitan'],
            'missing street' => ['unit', ''],
            'oversized street' => ['unit', str_repeat('a', 256)],
            'array city' => ['city', ['Angat']],
        ];
    }

    public function test_address_errors_preserve_selections_without_repopulating_password_fields(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->from(route('customer.settings'))->put(route('customer.settings.address'), [
            ...$this->address(), 'unit' => '',
        ])->assertSessionHasErrorsIn('address', ['unit' => 'Enter your house number, street, or subdivision.'])->assertRedirect(route('customer.settings'));
        $this->withCookie(config('session.cookie'), $this->app['session']->getId())
            ->get(route('customer.settings'))->assertOk()
            ->assertSee('value="Angat" data-barangays=', false)
            ->assertSee('value="Banaban" selected', false)
            ->assertSee('Enter your house number, street, or subdivision.');
    }

    public function test_password_change_hashes_the_new_password_and_preserves_profile_and_credit(): void
    {
        $customer = User::factory()->create(['credit_approved' => true, 'credit_limit' => 5000]);
        $token = $customer->remember_token;

        $this->actingAs($customer)->put(route('customer.settings.password'), [
            'current_password' => 'password', 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123',
            'role' => 'admin', 'credit_approved' => false,
        ])->assertRedirect(route('customer.settings').'#change-password')->assertSessionHas('success');

        $customer->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123', $customer->password));
        $this->assertFalse(Hash::check('password', $customer->password));
        $this->assertNotSame($token, $customer->remember_token);
        $this->assertTrue($customer->credit_approved);
        $this->assertSame('customer', $customer->role);
        $this->assertAuthenticatedAs($customer);
    }

    #[DataProvider('invalidPasswords')]
    public function test_password_change_rejects_invalid_credentials_and_weak_passwords(array $input, string $field): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->putJson(route('customer.settings.password'), $input)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertTrue(Hash::check('password', $customer->fresh()->password));
    }

    public static function invalidPasswords(): array
    {
        return [
            'wrong current' => [['current_password' => 'wrong', 'password' => 'NewSecure123', 'password_confirmation' => 'NewSecure123'], 'current_password'],
            'short' => [['current_password' => 'password', 'password' => 'Ab123', 'password_confirmation' => 'Ab123'], 'password'],
            'letters only' => [['current_password' => 'password', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password'],
            'numbers only' => [['current_password' => 'password', 'password' => '12345678', 'password_confirmation' => '12345678'], 'password'],
            'confirmation mismatch' => [['current_password' => 'password', 'password' => 'NewSecure123', 'password_confirmation' => 'Other123'], 'password'],
            'same password' => [['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'], 'password'],
            'too many bytes' => [['current_password' => 'password', 'password' => str_repeat('é', 40).'123', 'password_confirmation' => str_repeat('é', 40).'123'], 'password'],
        ];
    }

    public function test_password_change_removes_other_database_sessions_only_for_this_customer(): void
    {
        config(['session.driver' => 'database']);
        $customer = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'customer-other-session', 'user_id' => $customer->id, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'another-account-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($customer)->put(route('customer.settings.password'), [
            'current_password' => 'password', 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'customer-other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'another-account-session']);
        $this->assertAuthenticatedAs($customer->fresh());
    }

    public function test_profile_error_bag_does_not_expose_a_password_in_old_input(): void
    {
        $customer = User::factory()->create();
        $data = array_replace($this->profile($customer), ['current_password' => 'SecretWrong123']);

        $this->actingAs($customer)->put(route('customer.settings.update'), $data)
            ->assertSessionHasErrorsIn('account', 'current_password')
            ->assertSessionMissing('_old_input.current_password');
    }

    #[DataProvider('settingsRoutes')]
    public function test_guests_cannot_access_or_update_customer_settings(string $method, string $route): void
    {
        $this->{$method}(route($route))->assertRedirect(route('login'));
    }

    public static function settingsRoutes(): array
    {
        return [
            'view' => ['get', 'customer.settings'],
            'profile' => ['put', 'customer.settings.update'],
            'address' => ['put', 'customer.settings.address'],
            'password' => ['put', 'customer.settings.password'],
        ];
    }

    public function test_settings_escape_saved_profile_and_address_text(): void
    {
        $customer = User::factory()->create(['name' => '<script>alert(1)</script>', 'unit' => '"><script>alert(2)</script>']);

        $this->actingAs($customer)->get(route('customer.settings'))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('"><script>alert(2)</script>')->assertDontSee('"><script>alert(2)</script>', false);
    }

    public function test_cart_links_to_billing_settings_when_an_address_is_missing(): void
    {
        $customer = User::factory()->create();
        $product = Product::create(['name' => 'Address test product', 'price' => 100, 'stock_quantity' => 1]);
        $customer->cart()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($customer)->get(route('cart.index'))->assertOk()
            ->assertSee(route('customer.settings').'#billing-address');
    }

    public function test_password_changes_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->putJson(route('customer.settings.password'), [])->assertUnprocessable();
        }

        $this->putJson(route('customer.settings.password'), [])->assertTooManyRequests();
    }

    private function profile(User $user): array
    {
        return [
            'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'phone_no' => $user->phone_no, 'current_password' => 'password',
        ];
    }

    private function address(): array
    {
        return ['province' => 'Bulacan', 'city' => 'Angat', 'barangay' => 'Banaban', 'unit' => '12 Test Street'];
    }
}
