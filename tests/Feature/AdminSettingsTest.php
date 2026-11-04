<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\StoreSettingSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_view_the_account_security_and_store_forms_without_api_secrets(): void
    {
        config(['services.paymongo.secret' => 'sk_test_hidden_secret']);

        $this->actingAs($this->createUser())->get(route('admin.settings'))->assertOk()
            ->assertSee('Your account')->assertSee('Change password')->assertSee('Low-stock alert threshold')
            ->assertSee(route('admin.settings.password'))->assertSee(route('admin.settings.store'))
            ->assertDontSee('sk_test_hidden_secret');
    }

    public function test_account_update_requires_current_password_and_ignores_other_user_or_role_fields(): void
    {
        $admin = $this->createUser();
        $other = $this->createUser('customer');
        $otherUsername = $other->username;

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'name' => 'Store Administrator', 'username' => $admin->username,
            'email' => 'new-admin@example.test', 'phone_no' => '09123456780',
            'current_password' => 'password', 'role' => 'customer', 'id' => $other->id, 'password' => 'InjectedPassword123',
        ])->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Store Administrator', 'email' => 'new-admin@example.test', 'role' => 'admin']);
        $this->assertSame($otherUsername, $other->fresh()->username);
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_account_update_rejects_wrong_password_without_changing_details(): void
    {
        $admin = $this->createUser();
        $originalEmail = $admin->email;

        $this->actingAs($admin)->putJson(route('admin.settings.update'), [
            'username' => 'changed-admin', 'email' => 'changed@example.test', 'phone_no' => '09123456780', 'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertSame($originalEmail, $admin->fresh()->email);
    }

    public function test_duplicate_login_identifiers_are_rejected(): void
    {
        $admin = $this->createUser();
        $other = $this->createUser('customer');

        $this->actingAs($admin)->putJson(route('admin.settings.update'), [
            'username' => $other->username, 'email' => $other->email, 'phone_no' => $other->phone_no, 'current_password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'email', 'phone_no']);
    }

    public function test_password_form_changes_only_the_password_and_keeps_current_admin_signed_in(): void
    {
        $admin = $this->createUser();
        $oldToken = $admin->remember_token;
        $email = $admin->email;

        $this->actingAs($admin)->put(route('admin.settings.password'), [
            'current_password' => 'password', 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123',
        ])->assertRedirect(route('admin.settings'));

        $this->assertTrue(Hash::check('NewSecurePassword123', $admin->fresh()->password));
        $this->assertFalse(Hash::check('password', $admin->fresh()->password));
        $this->assertNotSame($oldToken, $admin->fresh()->remember_token);
        $this->assertSame($email, $admin->fresh()->email);
        $this->assertAuthenticatedAs($admin);
    }

    #[DataProvider('invalidPasswordChanges')]
    public function test_password_change_rejects_invalid_credentials_or_confirmation(array $input, string $field): void
    {
        $admin = $this->createUser();

        $this->actingAs($admin)->putJson(route('admin.settings.password'), $input)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public static function invalidPasswordChanges(): array
    {
        return [
            'wrong current password' => [['current_password' => 'wrong', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'], 'current_password'],
            'missing confirmation' => [['current_password' => 'password', 'password' => 'NewPassword123'], 'password'],
            'mismatched confirmation' => [['current_password' => 'password', 'password' => 'NewPassword123', 'password_confirmation' => 'OtherPassword123'], 'password'],
            'short password' => [['current_password' => 'password', 'password' => 'Ab123', 'password_confirmation' => 'Ab123'], 'password'],
            'no numbers' => [['current_password' => 'password', 'password' => 'OnlyLetters', 'password_confirmation' => 'OnlyLetters'], 'password'],
            'no letters' => [['current_password' => 'password', 'password' => '123456789', 'password_confirmation' => '123456789'], 'password'],
        ];
    }

    public function test_password_change_revokes_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->createUser();
        $other = $this->createUser('customer');
        DB::table('sessions')->insert([
            ['id' => 'old-admin-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'customer-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($admin)->put(route('admin.settings.password'), [
            'current_password' => 'password', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('admin.settings'));

        $this->assertDatabaseMissing('sessions', ['id' => 'old-admin-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'customer-session']);
    }

    public function test_store_settings_are_saved_with_the_admin_as_last_editor(): void
    {
        $admin = $this->createUser();
        $data = StoreSetting::factory()->raw([
            'store_name' => 'Lazaro Hardware Store', 'store_address' => 'San Miguel, Bulacan',
            'contact_phone' => '09123456789', 'contact_email' => 'shop@example.test',
            'opening_hours' => 'Monday to Saturday, 8 AM to 6 PM', 'low_stock_threshold' => 7,
        ]);

        $this->actingAs($admin)->put(route('admin.settings.store'), [...$data, 'updated_by' => 999])->assertRedirect(route('admin.settings'));

        $this->assertDatabaseHas('store_settings', [...$data, 'id' => 1, 'updated_by' => $admin->id]);
        $this->assertDatabaseCount('store_settings', 1);
    }

    public function test_store_details_are_used_in_customer_pages_and_finance_reports_and_escaped(): void
    {
        $admin = $this->createUser();
        $name = 'Lazaro <script>alert(1)</script>';
        $settings = StoreSetting::current();
        $settings->update(['store_name' => $name, 'contact_email' => 'shop@example.test', 'opening_hours' => 'Open daily 8 AM to 5 PM']);

        $this->get(route('products.index'))->assertOk()->assertSee($name)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('shop@example.test')->assertSee('Open daily 8 AM to 5 PM');
        $this->actingAs($admin)->get(route('admin.finance'))->assertOk()->assertViewHas('storeName', $name);
    }

    public function test_low_stock_threshold_controls_dashboard_including_zero(): void
    {
        $admin = $this->createUser();
        $empty = Product::create(['name' => 'Out of stock', 'price' => 10, 'stock_quantity' => 0]);
        Product::create(['name' => 'One remaining', 'price' => 10, 'stock_quantity' => 1]);
        $data = StoreSetting::factory()->raw(['low_stock_threshold' => 0]);

        $this->actingAs($admin)->put(route('admin.settings.store'), $data)->assertRedirect(route('admin.settings'));

        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('lowStockProducts', fn ($products) => $products->pluck('id')->all() === [$empty->id]);
    }

    #[DataProvider('invalidStoreSettings')]
    public function test_invalid_store_settings_do_not_overwrite_saved_values(string $field, mixed $value): void
    {
        $admin = $this->createUser();
        $data = StoreSetting::factory()->raw([$field => $value]);

        $this->actingAs($admin)->putJson(route('admin.settings.store'), $data)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame(StoreSetting::DEFAULTS['store_name'], StoreSetting::current()->store_name);
        $this->assertSame(20, StoreSetting::current()->low_stock_threshold);
    }

    public static function invalidStoreSettings(): array
    {
        return [
            'empty name' => ['store_name', ''], 'negative threshold' => ['low_stock_threshold', -1],
            'fractional threshold' => ['low_stock_threshold', 2.5], 'large threshold' => ['low_stock_threshold', 100001],
            'invalid email' => ['contact_email', 'not-email'], 'invalid contact' => ['contact_phone', 'hello'],
        ];
    }

    public function test_store_seeder_preserves_saved_settings(): void
    {
        StoreSetting::current()->update(['store_name' => 'Saved store', 'low_stock_threshold' => 3]);

        $this->seed(StoreSettingSeeder::class);

        $this->assertSame('Saved store', StoreSetting::current()->store_name);
        $this->assertSame(3, StoreSetting::current()->low_stock_threshold);
        $this->assertDatabaseCount('store_settings', 1);
    }

    #[DataProvider('settingsRoutes')]
    public function test_customers_cannot_manage_admin_settings(string $route, string $method): void
    {
        $this->actingAs($this->createUser('customer'))->{$method}(route($route))->assertForbidden();
    }

    #[DataProvider('settingsRoutes')]
    public function test_guests_cannot_manage_admin_settings(string $route, string $method): void
    {
        $this->{$method}(route($route))->assertRedirect(route('login'));
    }

    public static function settingsRoutes(): array
    {
        return [
            'view' => ['admin.settings', 'get'], 'account' => ['admin.settings.update', 'put'],
            'password' => ['admin.settings.password', 'put'], 'store' => ['admin.settings.store', 'put'],
        ];
    }

    private function createUser(string $role = 'admin'): User
    {
        $user = User::factory()->make(['username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'), 'role' => $role]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }
}
