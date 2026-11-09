<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditApplication;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RmLazaroSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signup_requires_strong_confirmed_password_and_does_not_accept_privileges(): void
    {
        $payload = $this->registration() + ['role' => 'admin', 'credit_approved' => true];

        $this->post('/signup', $payload)->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $user = User::firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->credit_approved);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
    }

    public function test_successful_login_upgrades_a_legacy_bcrypt_password_to_argon2id(): void
    {
        $customer = User::factory()->create([
            'password' => Hash::driver('bcrypt')->make('LegacyPassword123'),
        ]);

        $this->post('/login', ['login_id' => $customer->username, 'password' => 'LegacyPassword123'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame('argon2id', password_get_info($customer->fresh()->password)['algoName']);
        $this->assertTrue(Hash::check('LegacyPassword123', $customer->fresh()->password));
    }

    public function test_argon2id_signup_preserves_password_characters_after_72_bytes(): void
    {
        $password = str_repeat('a', 72).'First123';
        $payload = array_replace($this->registration(), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $this->post('/signup', $payload)->assertSessionHasNoErrors();

        $hash = User::firstOrFail()->password;
        $this->assertTrue(Hash::check($password, $hash));
        $this->assertFalse(Hash::check(str_repeat('a', 72).'Different123', $hash));
    }

    public function test_incorrect_legacy_password_does_not_login_or_replace_the_hash(): void
    {
        $hash = Hash::driver('bcrypt')->make('LegacyPassword123');
        $customer = User::factory()->create(['password' => $hash]);

        $this->post('/login', ['login_id' => $customer->username, 'password' => 'WrongPassword123'])
            ->assertSessionHasErrors('login_id');

        $this->assertGuest();
        $this->assertSame($hash, $customer->fresh()->password);
    }

    #[DataProvider('invalidRegistration')]
    public function test_invalid_registration_is_rejected(string $field, mixed $value): void
    {
        $payload = array_replace($this->registration(), [$field => $value]);

        $this->postJson('/signup', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors($field === 'password_confirmation' ? 'password' : $field);

        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidRegistration(): array
    {
        return [
            'phone username' => ['username', '09123456789'],
            'email username' => ['username', 'customer@example.test'],
            'username whitespace' => ['username', 'customer name'],
            'short password' => ['password', 'Abc123'],
            'only letters' => ['password', 'abcdefgh'],
            'only digits' => ['password', '12345678'],
            'confirmation mismatch' => ['password_confirmation', 'Different123'],
        ];
    }

    #[DataProvider('collisionFields')]
    public function test_login_uses_the_correct_identifier_even_with_a_legacy_username_collision(string $field, string $value): void
    {
        User::factory()->create(['username' => $value]);
        $owner = User::factory()->create([$field => $value, 'password' => Hash::make('OwnerPassword123')]);

        $this->post('/login', ['login_id' => $value, 'password' => 'OwnerPassword123'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($owner);
    }

    public static function collisionFields(): array
    {
        return ['phone' => ['phone_no', '09123456789'], 'email' => ['email', 'owner@example.test']];
    }

    public function test_login_throttles_password_spraying_across_different_identifiers(): void
    {
        $this->freezeTime();
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/login', ['login_id' => 'unknown'.$attempt, 'password' => 'WrongPassword123'])
                ->assertRedirect();
        }

        $this->postJson('/login', ['login_id' => 'another-user', 'password' => 'WrongPassword123'])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_failed_login_does_not_flash_the_password(): void
    {
        $this->post('/login', ['login_id' => 'missing', 'password' => 'PrivatePassword123'])
            ->assertSessionHasErrors('login_id')
            ->assertSessionHas('_old_input.login_id', 'missing')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_signup_has_an_ip_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/signup', [])->assertUnprocessable();
        }

        $this->postJson('/signup', $this->registration())->assertTooManyRequests();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_cannot_change_own_role_even_when_another_admin_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patchJson(route('admin.users.update', $admin), ['role' => 'customer', 'name' => 'Changed'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertNotSame('Changed', $admin->fresh()->name);
    }

    public function test_only_active_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $archived = User::factory()->create(['role' => 'admin']);
        $archived->archived_at = now();
        $archived->save();

        $this->actingAs($admin)->patchJson(route('admin.users.update', $admin), ['role' => 'customer'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->assertSame(1, User::where('role', 'admin')->whereNull('archived_at')->count());
    }

    public function test_admin_can_demote_another_admin_but_demoted_actor_cannot_demote_them_back(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.users.update', $other), ['role' => 'customer'])->assertSessionHasNoErrors();
        $this->actingAs($other)->patchJson(route('admin.users.update', $admin), ['role' => 'customer'])->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('customer', $other->fresh()->role);
    }

    public function test_admin_can_update_own_profile_without_changing_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), ['role' => 'admin', 'name' => 'Updated name'])->assertSessionHasNoErrors();

        $this->assertSame('Updated name', $admin->fresh()->name);
    }

    public function test_settings_rejects_a_phone_shaped_username(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->putJson(route('admin.settings.update'), [
            'username' => '09123456789', 'email' => $admin->email, 'phone_no' => $admin->phone_no, 'current_password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->assertSame($admin->username, $admin->fresh()->username);
    }

    #[DataProvider('uploadRoutes')]
    public function test_public_images_receive_random_filenames(string $route, string $model, string $directory): void
    {
        Storage::fake('public');
        $this->app->usePublicPath(Storage::disk('public')->path(''));
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->productPayload() + ['image' => UploadedFile::fake()->image('customer-original.jpg')];

        $this->actingAs($admin)->post(route($route), $payload)->assertSessionHasNoErrors();

        $record = $model::latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('#^'.$directory.'/[A-Za-z0-9]{40}\\.jpg$#', $record->image_path);
        $this->assertStringNotContainsString('customer-original', $record->image_path);
        Storage::disk('public')->assertExists($record->image_path);
    }

    #[DataProvider('unsafeUploads')]
    public function test_public_uploads_reject_executable_and_mismatched_extensions(string $route, string $filename): void
    {
        Storage::fake('public');
        $this->app->usePublicPath(Storage::disk('public')->path(''));
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->productPayload() + ['image' => UploadedFile::fake()->image($filename)];

        $this->actingAs($admin)->postJson(route($route), $payload)->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function uploadRoutes(): array
    {
        return [
            'product' => ['admin.inventory.store', Product::class, 'product-images'],
            'category' => ['admin.categories.store', Category::class, 'category-images'],
        ];
    }

    public static function unsafeUploads(): array
    {
        return [
            'product php' => ['admin.inventory.store', 'shell.php'],
            'category php' => ['admin.categories.store', 'shell.php'],
            'product phtml' => ['admin.inventory.store', 'shell.phtml'],
            'category html' => ['admin.categories.store', 'image.html'],
        ];
    }

    public function test_customer_cannot_upload_product_images(): void
    {
        Storage::fake('public');
        $this->app->usePublicPath(Storage::disk('public')->path(''));

        $this->actingAs(User::factory()->create())->postJson(route('admin.inventory.store'), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_variant_stock_must_be_an_integer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->productPayload() + [
            'option_types' => [['name' => 'Size', 'values' => 'Large']],
            'variants' => [['options' => '{"Size":"Large"}', 'stock_quantity' => 1.5]],
        ];

        $this->actingAs($admin)->postJson(route('admin.inventory.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('variants.0.stock_quantity');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_application_accepts_own_phone_and_keeps_documents_private(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('credit.apply.store'), $this->applicationPayload($customer->phone_no))
            ->assertSessionHasNoErrors()->assertRedirect(route('credit.apply'));

        $application = CreditApplication::firstOrFail();
        $this->assertSame($customer->id, $application->user_id);
        $this->assertSame($customer->phone_no, $application->phone_number);
        $this->assertSame('credit-privacy-v2', $application->privacy_notice_version);
        $this->assertTrue($application->privacy_notice_accepted_at->isSameSecond(now()));
        Storage::disk('local')->assertExists([$application->id_photo_path, $application->selfie_photo_path]);
    }

    public function test_application_requires_privacy_consent_before_storing_documents(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create();
        $payload = $this->applicationPayload($customer->phone_no);
        unset($payload['accept_credit_privacy']);

        $this->actingAs($customer)->postJson(route('credit.apply.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('accept_credit_privacy');

        $this->assertDatabaseCount('credit_applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_application_shows_completed_privacy_notice_and_configured_contact(): void
    {
        StoreSetting::current()->update([
            'contact_phone' => '0917 111 2222',
            'contact_email' => 'privacy@example.test',
            'store_address' => 'Test address <script>alert(1)</script>',
        ]);

        $this->actingAs(User::factory()->create()->fresh())->get(route('credit.apply'))
            ->assertOk()
            ->assertSee('credit-privacy-v2')
            ->assertSee('privacy@example.test')
            ->assertSee('0917 111 2222')
            ->assertSee('Test address <script>alert(1)</script>')
            ->assertDontSee('Test address <script>alert(1)</script>', false)
            ->assertSee('no automatic deletion schedule')
            ->assertSee('Consent and withdrawal')
            ->assertDontSee('[confirm and state')
            ->assertDontSee('[insert the actual retention');
    }

    public function test_application_rejects_another_users_phone_number(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($customer)->postJson(route('credit.apply.store'), $this->applicationPayload($other->phone_no))
            ->assertUnprocessable()->assertJsonValidationErrors('phone_number');

        $this->assertDatabaseCount('credit_applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[DataProvider('demoSeeders')]
    public function test_demo_seeders_refuse_production_before_writing_any_records(string $seeder): void
    {
        $this->assertDatabaseCount('users', 0);
        $this->app->instance('env', 'production');
        try {
            $this->artisan('db:seed', ['--class' => $seeder, '--force' => true, '--no-interaction' => true])->run();
            $this->fail('Production demo seeding should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Demo accounts may only be seeded in local or testing environments.', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public static function demoSeeders(): array
    {
        return ['default seeder' => [DatabaseSeeder::class], 'direct seeder' => [RmLazaroSeeder::class]];
    }

    public function test_demo_seeder_still_works_in_testing(): void
    {
        $this->seed(RmLazaroSeeder::class);

        $this->assertDatabaseHas('users', ['username' => 'admin', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['username' => 'user', 'role' => 'customer']);
        $this->assertDatabaseCount('products', 10);
    }

    #[DataProvider('literalCharacters')]
    public function test_admin_search_treats_wildcards_as_literal_text(string $character): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'administrator']);
        $match = User::factory()->create(['username' => 'target'.$character.'name']);
        User::factory()->create(['username' => 'targetXname']);
        $order = $match->orders()->create(['total_amount' => 10, 'payment_method' => 'cod', 'order_status' => 'pending', 'order_date' => now()]);
        $product = Product::create(['name' => 'Product'.$character.'name', 'price' => 10, 'stock_quantity' => 1]);
        Product::create(['name' => 'ProductXname', 'price' => 10, 'stock_quantity' => 1]);

        $this->actingAs($admin)->get(route('admin.users', ['search' => $character]))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->modelKeys() === [$match->id]);
        $this->get(route('admin.orders', ['search' => $character]))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->modelKeys() === [$order->id]);
        $this->get(route('admin.inventory', ['search' => $character]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$product->id]);
    }

    public static function literalCharacters(): array
    {
        return ['percent' => ['%'], 'underscore' => ['_'], 'escape character' => ['!']];
    }

    private function registration(): array
    {
        return [
            'username' => 'customer_one', 'email' => 'customer@example.test', 'phone_no' => '09123456789',
            'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123',
        ];
    }

    private function productPayload(): array
    {
        return [
            'name' => 'Test product', 'category_id' => Category::create(['name' => 'Test category'])->id,
            'price' => '100.00', 'stock_quantity' => 10,
        ];
    }

    private function applicationPayload(string $phone): array
    {
        return [
            'first_name' => 'Juan', 'last_name' => 'Customer', 'phone_number' => $phone,
            'province' => 'Bulacan', 'city' => 'San Miguel', 'barangay' => 'Test barangay', 'street' => 'Test street',
            'credit_due_day' => '15', 'id_type' => 'national_id',
            'id_photo' => UploadedFile::fake()->image('id.jpg'), 'selfie_photo' => UploadedFile::fake()->image('selfie.jpg'),
            'accept_paylater_terms' => 1, 'accept_credit_privacy' => 1,
        ];
    }
}
