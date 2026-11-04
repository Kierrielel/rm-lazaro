<?php

namespace Tests\Feature;

use App\Models\CreditPaymentAttempt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayLaterCheckoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret' => 'sk_test_server_only']);
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00'));
    }

    public function test_pay_now_redirects_to_hosted_checkout_with_server_calculated_amount(): void
    {
        $user = $this->customer();
        $this->order($user);
        Http::fake([
            'https://api.paymongo.com/v2/checkout_sessions' => Http::response(['data' => [
                'id' => 'cs_test', 'type' => 'checkout_session',
                'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test-session', 'livemode' => false],
            ]], 201),
            'https://api.paymongo.com/v1/checkout_sessions/cs_test' => fn () => Http::response($this->checkoutResponse(CreditPaymentAttempt::firstOrFail())),
        ]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), [
            'method' => 'qrph', 'amount_cents' => 1, 'user_id' => 999,
        ])->assertStatus(303)->assertRedirect('https://checkout.paymongo.com/test-session');

        $this->assertDatabaseHas('credit_payment_attempts', ['user_id' => $user->id, 'amount_cents' => 11050, 'status' => 'pending']);
        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && data_get($request->data(), 'data.attributes.line_items.0.amount') === 11050
            && data_get($request->data(), 'data.attributes.payment_method_types') === ['qrph']
            && data_get($request->data(), 'data.attributes.success_url') === route('paylater.payment-attempts.show', CreditPaymentAttempt::first()->reference)
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_server_only:')));
    }

    public function test_repeated_pay_now_reuses_the_saved_checkout_instead_of_creating_another(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt))]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])
            ->assertRedirect('https://checkout.paymongo.com/test-session');

        $this->assertDatabaseCount('credit_payment_attempts', 1);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_return_url_does_not_record_payment_without_provider_confirmation(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt))]);

        $this->actingAs($user)->get(route('paylater.payment-attempts.show', $attempt->reference).'?status=succeeded&paid=1')
            ->assertOk()->assertSee('Payment has not been confirmed');

        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'status' => 'pending']);
        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    public function test_verified_payment_updates_installment_once_on_repeated_returns(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt, true))]);

        $this->actingAs($user)->get(route('paylater.payment-attempts.show', $attempt->reference))->assertOk()->assertSee('Payment verified');
        $this->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'succeeded']);

        $this->assertDatabaseCount('credit_payments', 1);
        $this->assertDatabaseHas('credit_payments', ['amount' => 110.50]);
        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'paymongo_payment_id' => 'pay_test', 'status' => 'succeeded']);
        $this->assertSame(221.0, $attempt->allocations()->first()->installment->order->balance);
        Http::assertSentCount(1);
    }

    public function test_closed_checkout_can_be_verified_by_scheduled_command_without_a_return(): void
    {
        $attempt = $this->attempt($this->customer());
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt, true))]);

        $this->artisan('paylater:reconcile')->assertSuccessful();

        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'status' => 'succeeded']);
        $this->assertDatabaseCount('credit_payments', 1);
        Http::assertSentCount(1);
    }

    public function test_customer_can_resume_a_closed_browser_checkout(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt))]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.resume', $attempt->reference))
            ->assertStatus(303)->assertRedirect('https://checkout.paymongo.com/test-session');

        $this->assertDatabaseCount('credit_payment_attempts', 1);
        Http::assertSentCount(1);
    }

    #[DataProvider('verificationMismatches')]
    public function test_unmatched_provider_payment_requires_review_without_creating_ledger(string $path, mixed $value): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $session = $this->checkoutResponse($attempt, true);
        data_set($session, $path, $value);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($session)]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'review']);

        $this->assertDatabaseCount('credit_payments', 0);
        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'status' => 'review']);
        Http::assertSentCount(1);
    }

    public static function verificationMismatches(): array
    {
        return [
            'checkout id' => ['data.id', 'cs_someone_else'],
            'reference' => ['data.attributes.reference_number', 'another-reference'],
            'line item amount' => ['data.attributes.line_items.0.amount', 100],
            'payment amount' => ['data.attributes.payments.0.attributes.amount', 100],
            'payment currency' => ['data.attributes.payments.0.attributes.currency', 'USD'],
            'payment intent identity' => ['data.attributes.payments.0.attributes.payment_intent_id', 'pi_other'],
            'intent amount' => ['data.attributes.payment_intent.attributes.amount', 100],
            'test/live mode' => ['data.attributes.livemode', true],
            'payment mode' => ['data.attributes.payments.0.attributes.livemode', true],
            'unsuccessful intent' => ['data.attributes.payment_intent.attributes.status', 'processing'],
            'refunded payment' => ['data.attributes.payments.0.attributes.refunds', [['id' => 'ref_test']]],
        ];
    }

    public function test_provider_outage_leaves_payment_pending_and_retryable(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response([], 503)]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertStatus(502);

        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'status' => 'pending']);
        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    public function test_only_provider_expired_checkout_is_closed_as_failed(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $session = $this->checkoutResponse($attempt);
        data_set($session, 'data.attributes.status', 'expired');
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($session)]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'failed']);

        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    public function test_local_expiry_does_not_close_an_active_checkout(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $attempt->update(['expires_at' => now()->subHours(2)]);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt))]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'pending']);

        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    #[DataProvider('ownedRoutes')]
    public function test_customer_cannot_access_another_customers_payment(string $route, string $method): void
    {
        $attempt = $this->attempt($this->customer());
        $otherUser = $this->customer();

        $this->actingAs($otherUser)->{$method}(route($route, $attempt->reference))->assertNotFound();

        Http::assertNothingSent();
        $this->assertDatabaseCount('credit_payments', 0);
    }

    public static function ownedRoutes(): array
    {
        return [
            'details' => ['paylater.payment-attempts.show', 'get'],
            'status' => ['paylater.payment-attempts.status', 'getJson'],
            'resume' => ['paylater.payment-attempts.resume', 'post'],
        ];
    }

    public function test_guest_cannot_start_a_payment(): void
    {
        $this->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_unapproved_customer_cannot_start_payment(): void
    {
        $user = $this->customer();
        $user->update(['credit_approved' => false]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_disabled_wallet_methods_are_rejected_server_side(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'gcash'])->assertSessionHasErrors('method');

        Http::assertNothingSent();
    }

    public function test_no_due_installments_prevents_checkout_creation(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertSessionHasErrors('payment');

        Http::assertNothingSent();
        $this->assertDatabaseCount('credit_payment_attempts', 0);
    }

    public function test_uncertain_checkout_creation_is_not_retried_as_a_new_charge(): void
    {
        $user = $this->customer();
        $this->order($user);
        Http::fake(['https://api.paymongo.com/v2/checkout_sessions' => Http::failedConnection()]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertSessionHasErrors('payment');
        $attempt = CreditPaymentAttempt::firstOrFail();
        $this->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertRedirect(route('paylater.payment-attempts.show', $attempt->reference));

        $this->assertDatabaseCount('credit_payment_attempts', 1);
        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'status' => 'review']);
        Http::assertSentCount(1);
    }

    public function test_payment_allocation_changes_require_review_instead_of_overpayment(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $attempt->allocations()->first()->installment->payments()->create(['order_id' => $attempt->allocations()->first()->installment->order_id, 'amount' => 10, 'paid_at' => now()]);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt, true))]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'review']);

        $this->assertDatabaseCount('credit_payments', 1);
        Http::assertSentCount(1);
    }

    public function test_account_page_shows_saved_checkout_and_history_after_browser_closes(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt))]);

        $this->actingAs($user)->get(route('credit.apply'))->assertOk()
            ->assertSee('Check or continue payment')->assertSee('Recent payments')
            ->assertSee(route('paylater.payment-attempts.show', $attempt->reference))
            ->assertDontSee('id="paylater-payment-form"', false)->assertDontSee('sk_test_server_only');

        Http::assertSentCount(1);
    }

    public function test_full_payoff_clears_hold_only_after_all_allocations_are_paid(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $order->installments()->first()->update(['due_date' => today()->subDay()]);
        $checks = 0;
        Http::fake([
            'https://api.paymongo.com/v2/checkout_sessions' => fn () => Http::response($this->checkoutResponse(CreditPaymentAttempt::firstOrFail(), false, 33150), 201),
            'https://api.paymongo.com/v1/checkout_sessions/cs_test' => function () use (&$checks) {
                return Http::response($this->checkoutResponse(CreditPaymentAttempt::firstOrFail(), $checks++ > 0, 33150));
            },
        ]);
        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertStatus(303);
        $attempt = CreditPaymentAttempt::firstOrFail();
        $this->assertTrue($user->fresh()->credit_on_hold);
        $this->assertSame(33150, $attempt->amount_cents);

        $this->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'succeeded']);

        $this->assertDatabaseCount('credit_payments', 3);
        $this->assertTrue($order->fresh()->is_fully_paid);
        $this->assertFalse($user->fresh()->credit_on_hold);
        Http::assertSentCount(3);
    }

    public function test_reviewed_checkout_can_resume_only_after_full_server_verification(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $attempt->update(['status' => 'review', 'checkout_url' => null]);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt))]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.resume', $attempt->reference))
            ->assertRedirect('https://checkout.paymongo.com/test-session');

        $this->assertDatabaseHas('credit_payment_attempts', ['id' => $attempt->id, 'status' => 'pending']);
        $this->assertDatabaseCount('credit_payment_attempts', 1);
        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_untrusted_checkout_url_is_not_used_for_redirect(): void
    {
        $user = $this->customer();
        $this->order($user);
        Http::fake(['https://api.paymongo.com/v2/checkout_sessions' => function () {
            $response = $this->checkoutResponse(CreditPaymentAttempt::firstOrFail());
            data_set($response, 'data.attributes.checkout_url', 'https://attacker.example/payment');

            return Http::response($response, 201);
        }]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])
            ->assertRedirect(route('credit.apply'))->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('credit_payment_attempts', ['status' => 'review', 'checkout_url' => null]);
        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    public function test_explicit_checkout_rejection_allows_a_new_attempt(): void
    {
        $user = $this->customer();
        $this->order($user);
        Http::fake(['https://api.paymongo.com/v2/checkout_sessions' => Http::response(['errors' => []], 422)]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.store'), ['method' => 'qrph'])->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('credit_payment_attempts', ['status' => 'failed']);
        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    public function test_already_paid_checkout_is_not_reopened_when_customer_resumes(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($this->checkoutResponse($attempt, true))]);

        $this->actingAs($user)->post(route('paylater.payment-attempts.resume', $attempt->reference))
            ->assertRedirect(route('paylater.payment-attempts.show', $attempt->reference));

        $this->assertDatabaseCount('credit_payments', 1);
        Http::assertSentCount(1);
    }

    public function test_previous_payment_intent_can_still_be_verified_without_creating_checkout(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $attempt->update(['paymongo_checkout_session_id' => null, 'checkout_url' => null, 'paymongo_payment_intent_id' => 'pi_test']);
        $session = $this->checkoutResponse($attempt, true);
        $intent = data_get($session, 'data.attributes.payment_intent');
        $intent['attributes']['payments'] = data_get($session, 'data.attributes.payments');
        Http::fake(['https://api.paymongo.com/v1/payment_intents/pi_test' => Http::response(['data' => $intent])]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'succeeded']);

        $this->assertDatabaseCount('credit_payments', 1);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_missing_payment_proof_does_not_credit_a_succeeded_intent_alone(): void
    {
        $user = $this->customer();
        $attempt = $this->attempt($user);
        $session = $this->checkoutResponse($attempt, true);
        data_set($session, 'data.attributes.payments', []);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test' => Http::response($session)]);

        $this->actingAs($user)->getJson(route('paylater.payment-attempts.status', $attempt->reference))->assertExactJson(['status' => 'pending']);

        $this->assertDatabaseCount('credit_payments', 0);
        Http::assertSentCount(1);
    }

    private function customer(): User
    {
        $user = User::factory()->make([
            'username' => fake()->unique()->userName(), 'phone_no' => fake()->unique()->numerify('09#########'),
            'credit_approved' => true, 'credit_limit' => 10000, 'credit_due_day' => 'last_day',
        ]);
        unset($user->email_verified_at);
        $user->save();

        return $user->refresh();
    }

    private function order(User $user): Order
    {
        $order = $user->orders()->create([
            'total_amount' => 300, 'credit_finance_charge' => 31.50, 'credit_term_months' => 3,
            'credit_interest_rate' => 3.5, 'payment_method' => 'paylater', 'order_status' => 'pending',
            'order_date' => now(), 'due_date' => today(),
        ]);
        foreach (range(1, 3) as $number) {
            $order->installments()->create([
                'installment_number' => $number, 'amount_due' => 110.50,
                'due_date' => today()->addMonthsNoOverflow($number - 1),
            ]);
        }

        return $order;
    }

    private function attempt(User $user): CreditPaymentAttempt
    {
        $order = $this->order($user);
        $attempt = $user->creditPaymentAttempts()->create([
            'reference' => (string) Str::uuid(), 'method' => 'qrph', 'amount_cents' => 11050,
            'currency' => 'PHP', 'status' => 'pending', 'paymongo_checkout_session_id' => 'cs_test',
            'checkout_url' => 'https://checkout.paymongo.com/test-session',
        ]);
        $attempt->allocations()->create(['credit_installment_id' => $order->installments()->first()->id, 'amount_cents' => 11050]);

        return $attempt;
    }

    private function checkoutResponse(CreditPaymentAttempt $attempt, bool $paid = false, int $amount = 11050): array
    {
        return ['data' => [
            'id' => 'cs_test', 'type' => 'checkout_session',
            'attributes' => [
                'reference_number' => $attempt->reference, 'livemode' => false, 'status' => 'active',
                'checkout_url' => 'https://checkout.paymongo.com/test-session',
                'line_items' => [['amount' => $amount, 'currency' => 'PHP', 'quantity' => 1]],
                'payment_method_types' => ['qrph'],
                'payment_intent' => $paid ? [
                    'id' => 'pi_test', 'attributes' => ['amount' => $amount, 'currency' => 'PHP', 'livemode' => false, 'status' => 'succeeded'],
                ] : null,
                'payments' => $paid ? [[
                    'id' => 'pay_test', 'type' => 'payment', 'attributes' => [
                        'amount' => $amount, 'currency' => 'PHP', 'livemode' => false,
                        'status' => 'paid', 'payment_intent_id' => 'pi_test', 'source' => ['type' => 'qrph'],
                        'paid_at' => now()->timestamp, 'refunds' => [],
                    ],
                ]] : [],
            ],
        ]];
    }
}
