<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CreditApplication;
use App\Models\CreditPaymentAttempt;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreditApplicationRevocationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_revoke_disables_new_credit_and_records_audit_without_erasing_debt(): void
    {
        $this->freezeTime();
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $application = $this->application($customer);
        $reviewedAt = $application->reviewed_at->toDateTimeString();
        $order = $this->debt($customer);
        $order->installments()->first()->payments()->create(['order_id' => $order->id, 'amount' => 25, 'paid_at' => now()]);
        $customer->update(['credit_on_hold' => true]);

        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), [
            'revocation_reason' => 'Credit approval withdrawn after review.', 'revoked_by' => $customer->id,
        ])->assertRedirect(route('admin.credit-applications.show', $application));

        $this->assertFalse($customer->fresh()->credit_approved);
        $this->assertFalse($customer->fresh()->canUseCredit(1));
        $this->assertSame('5000.00', $customer->fresh()->credit_limit);
        $this->assertSame('last_day', $customer->fresh()->credit_due_day);
        $this->assertTrue($customer->fresh()->credit_on_hold);
        $this->assertSame('revoked', $application->fresh()->display_status);
        $this->assertSame($reviewedAt, $application->fresh()->reviewed_at->toDateTimeString());
        $this->assertDatabaseHas('credit_applications', ['id' => $application->id, 'revoked_by' => $admin->id, 'revocation_reason' => 'Credit approval withdrawn after review.']);
        $this->assertDatabaseCount('credit_installments', 3);
        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertSame(275.0, $order->fresh()->balance);
    }

    public function test_review_shows_revoke_option_for_current_approved_application(): void
    {
        $admin = $this->createUser('admin');
        $application = $this->application($this->createUser());

        $this->actingAs($admin)->get(route('admin.credit-applications.show', $application))->assertOk()
            ->assertSee('Revoke Pay Later access')->assertSee(route('admin.credit-applications.revoke', $application));
    }

    public function test_revocation_result_shows_reason_and_admin_without_another_revoke_form(): void
    {
        $admin = $this->createUser('admin');
        $application = $this->application($this->createUser());
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => '<script>alert(1)</script>']);

        $this->get(route('admin.credit-applications.show', $application))->assertOk()
            ->assertSee('Revoked')->assertSee($admin->username)->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee(route('admin.credit-applications.revoke', $application));
    }

    #[DataProvider('nonApprovedStatuses')]
    public function test_pending_and_rejected_applications_cannot_be_revoked(string $status): void
    {
        $admin = $this->createUser('admin');
        $application = $this->application($this->createUser(), $status);

        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Review'])->assertConflict();

        $this->assertNull($application->fresh()->revoked_at);
        $this->get(route('admin.credit-applications.show', $application))->assertOk()->assertDontSee('Revoke Pay Later access');
    }

    public static function nonApprovedStatuses(): array
    {
        return ['pending' => ['pending'], 'rejected' => ['rejected']];
    }

    public function test_repeating_revoke_does_not_overwrite_the_original_audit(): void
    {
        $admin = $this->createUser('admin');
        $application = $this->application($this->createUser());
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Original reason']);

        $this->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Replacement reason'])->assertConflict();

        $this->assertSame('Original reason', $application->fresh()->revocation_reason);
    }

    public function test_old_approval_cannot_disable_a_more_recent_approval(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $old = $this->application($customer);
        $this->application($customer);

        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $old), ['revocation_reason' => 'Old application'])->assertConflict();

        $this->assertTrue($customer->fresh()->credit_approved);
        $this->get(route('admin.credit-applications.show', $old))->assertOk()->assertDontSee('Revoke Pay Later access');
    }

    #[DataProvider('invalidReasons')]
    public function test_revocation_requires_a_valid_reason(mixed $reason): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $application = $this->application($customer);

        $this->actingAs($admin)->patchJson(route('admin.credit-applications.revoke', $application), ['revocation_reason' => $reason])
            ->assertUnprocessable()->assertJsonValidationErrors('revocation_reason');

        $this->assertTrue($customer->fresh()->credit_approved);
        $this->assertNull($application->fresh()->revoked_at);
    }

    public static function invalidReasons(): array
    {
        return ['empty' => [''], 'too long' => [str_repeat('a', 2001)], 'array' => [['invalid']]];
    }

    public function test_customer_cannot_revoke_their_own_application(): void
    {
        $customer = $this->createUser();
        $application = $this->application($customer);

        $this->actingAs($customer)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Forged request'])->assertForbidden();

        $this->assertTrue($customer->fresh()->credit_approved);
    }

    public function test_guest_cannot_revoke_an_application(): void
    {
        $application = $this->application($this->createUser());

        $this->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Guest'])->assertRedirect(route('login'));

        $this->assertNull($application->fresh()->revoked_at);
    }

    public function test_application_list_filters_revoked_separately_from_approved(): void
    {
        $admin = $this->createUser('admin');
        $revoked = $this->application($this->createUser());
        $approved = $this->application($this->createUser());
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $revoked), ['revocation_reason' => 'Review']);

        $this->get(route('admin.credit-applications.index', ['status' => 'revoked']))->assertOk()
            ->assertViewHas('applications', fn ($applications) => $applications->pluck('id')->all() === [$revoked->id]);
        $this->get(route('admin.credit-applications.index', ['status' => 'approved']))->assertOk()
            ->assertViewHas('applications', fn ($applications) => $applications->pluck('id')->all() === [$approved->id]);
    }

    public function test_revoked_customer_still_sees_bill_and_payment_controls_with_zero_available_credit(): void
    {
        $this->freezeTime();
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $application = $this->application($customer);
        $this->debt($customer);
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Approval withdrawn']);

        $this->actingAs($customer->fresh())->get(route('credit.apply'))->assertOk()
            ->assertSee('Your Pay Later access has been revoked.')->assertSee('Approval withdrawn')
            ->assertSee('Pay now')->assertViewHas('hasCreditAccount', true)->assertViewHas('availableCredit', 0);
    }

    public function test_revoked_customer_cannot_place_a_new_pay_later_order(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $application = $this->application($customer);
        $product = Product::create(['name' => 'Test product', 'price' => 50, 'stock_quantity' => 20]);
        Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Approval withdrawn']);

        $this->actingAs($customer->fresh())->post(route('checkout.store'), ['payment_method' => 'paylater', 'credit_term_months' => 3, 'confirm_credit_plan' => 1])
            ->assertRedirect(route('cart.index'))->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart', 1);
        $this->assertSame(20, $product->fresh()->stock_quantity);
    }

    public function test_revoked_customer_can_pay_existing_debt_and_payment_does_not_restore_credit(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        config(['services.paymongo.secret' => 'sk_test_revocation']);
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $application = $this->application($customer);
        $order = $this->debt($customer);
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Approval withdrawn']);
        $paid = false;
        Http::fake([
            'https://api.paymongo.com/v2/checkout_sessions' => Http::response(['data' => [
                'id' => 'cs_revoke', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/revoke-test'],
            ]], 201),
            'https://api.paymongo.com/v1/checkout_sessions/cs_revoke' => function () use (&$paid) {
                $attempt = CreditPaymentAttempt::firstOrFail();

                return Http::response(['data' => ['id' => 'cs_revoke', 'type' => 'checkout_session', 'attributes' => [
                    'reference_number' => $attempt->reference, 'livemode' => false, 'status' => 'active',
                    'checkout_url' => 'https://checkout.paymongo.com/revoke-test', 'payment_method_types' => ['qrph'],
                    'line_items' => [['amount' => 10000, 'currency' => 'PHP', 'quantity' => 1]],
                    'payment_intent' => $paid ? ['id' => 'pi_revoke', 'attributes' => ['amount' => 10000, 'currency' => 'PHP', 'livemode' => false, 'status' => 'succeeded']] : null,
                    'payments' => $paid ? [['id' => 'pay_revoke', 'attributes' => [
                        'status' => 'paid', 'amount' => 10000, 'currency' => 'PHP', 'livemode' => false,
                        'payment_intent_id' => 'pi_revoke', 'source' => ['type' => 'qrph'], 'refunds' => [],
                    ]]] : [],
                ]]]);
            },
        ]);

        $this->actingAs($customer->fresh())->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertRedirect('https://checkout.paymongo.com/revoke-test');
        $paid = true;
        $attempt = CreditPaymentAttempt::firstOrFail();
        $this->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'succeeded']);

        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertSame(200.0, $order->fresh()->balance);
        $this->assertFalse($customer->fresh()->credit_approved);
        Http::assertSentCount(3);
    }

    public function test_new_application_can_be_approved_without_overwriting_old_revocation(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $old = $this->application($customer);
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $old), ['revocation_reason' => 'Original revocation']);
        $new = $this->application($customer, 'pending');

        $this->patch(route('admin.credit-applications.approve', $new), ['credit_limit' => 5000])->assertRedirect();

        $this->assertTrue($customer->fresh()->credit_approved);
        $this->assertSame('Original revocation', $old->fresh()->revocation_reason);
        $this->assertNotNull($old->fresh()->revoked_at);
    }

    public function test_revoked_customer_retains_payment_history_after_paying_the_full_balance(): void
    {
        $this->freezeTime();
        $admin = $this->createUser('admin');
        $customer = $this->createUser();
        $application = $this->application($customer);
        $order = $this->debt($customer);
        $attempt = $customer->creditPaymentAttempts()->create([
            'reference' => (string) Str::uuid(), 'method' => 'qrph', 'amount_cents' => 30000,
            'currency' => 'PHP', 'status' => 'succeeded', 'paid_at' => now(),
        ]);
        foreach ($order->installments as $installment) {
            $installment->payments()->create(['order_id' => $order->id, 'amount' => 100, 'paid_at' => now()]);
            $attempt->allocations()->create(['credit_installment_id' => $installment->id, 'amount_cents' => 10000]);
        }
        $this->actingAs($admin)->patch(route('admin.credit-applications.revoke', $application), ['revocation_reason' => 'Approval withdrawn']);

        $this->actingAs($customer->fresh())->get(route('credit.apply'))->assertOk()
            ->assertSee('Recent payments')->assertSee(route('paylater.payment-attempts.show', $attempt->reference))
            ->assertViewHas('hasCreditAccount', false);

        $this->assertDatabaseCount('credit_payments', 3);
        $this->assertFalse($customer->fresh()->credit_approved);
    }

    private function createUser(string $role = 'customer'): User
    {
        $user = User::factory()->make([
            'username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'),
            'role' => $role, 'credit_approved' => true, 'credit_limit' => 5000, 'credit_due_day' => 'last_day',
        ]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }

    private function application(User $user, string $status = 'approved'): CreditApplication
    {
        $application = $user->creditApplications()->make([
            'first_name' => 'Juan', 'last_name' => 'Customer', 'phone_number' => '09123456789',
            'province' => 'Bulacan', 'city' => 'San Miguel', 'barangay' => 'Test barangay', 'street' => 'Test street',
            'credit_due_day' => 'last_day', 'id_type' => 'national_id',
        ]);
        $application->status = $status;
        $application->id_photo_path = 'credit-applications/'.$user->id.'/id.jpg';
        $application->selfie_photo_path = 'credit-applications/'.$user->id.'/selfie.jpg';
        $application->reviewed_at = $status === 'pending' ? null : now()->subDay();
        $application->save();

        return $application;
    }

    private function debt(User $user): Order
    {
        $order = $user->orders()->create(['total_amount' => 300, 'payment_method' => 'paylater', 'order_status' => 'pending', 'order_date' => now()]);
        foreach (range(1, 3) as $number) {
            $order->installments()->create(['installment_number' => $number, 'due_date' => today()->addMonthsNoOverflow($number - 1), 'amount_due' => 100]);
        }

        return $order;
    }
}
