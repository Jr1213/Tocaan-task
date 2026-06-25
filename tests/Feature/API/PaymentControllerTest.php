<?php

namespace Tests\Feature\API;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): static
    {
        $token = JWTAuth::fromUser($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_paying_with_cash_on_delivery_creates_a_pending_payment(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => 42.00]);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::CashOnDelivery->value,
            ])
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully.',
                'data'    => [
                    'order_id' => $order->id,
                    'method'   => PaymentMethod::CashOnDelivery->value,
                    'status'   => PaymentStatus::Pending->value,
                    'amount'   => '42.00',
                ],
            ])
            ->assertJsonPath('data.transaction_reference', null);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method'   => PaymentMethod::CashOnDelivery->value,
            'status'   => PaymentStatus::Pending->value,
        ]);
    }

    public function test_paying_with_stripe_marks_the_payment_paid_with_a_reference(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => 99.99]);

        $response = $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'method' => PaymentMethod::Stripe->value,
                    'status' => PaymentStatus::Paid->value,
                    'amount' => '99.99',
                ],
            ]);

        $this->assertNotNull($response->json('data.transaction_reference'));
        $this->assertStringStartsWith('pi_', $response->json('data.transaction_reference'));
        $this->assertNotNull($response->json('data.paid_at'));
    }

    public function test_payment_amount_matches_the_order_total(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => 123.45]);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '123.45');
    }

    public function test_an_order_can_only_be_paid_once(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        Payment::factory()->for($order)->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_requires_a_valid_method(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => 'bitcoin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_payment_requires_a_method(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_payment_is_forbidden_for_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(); // belongs to someone else

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->postJson(route('orders.payment.store', $order), [
            'payment_method' => PaymentMethod::Stripe->value,
        ])->assertUnauthorized();
    }

    public function test_order_show_includes_its_payment(): void
    {
        $user    = User::factory()->create();
        $order   = Order::factory()->for($user)->create();
        $payment = Payment::factory()->for($order)->create([
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Paid,
        ]);

        $this->actingAsUser($user)
            ->getJson(route('orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.payment.id', $payment->id)
            ->assertJsonPath('data.payment.method', PaymentMethod::Stripe->value);
    }
}
