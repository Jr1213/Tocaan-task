<?php

namespace Tests\Feature\API;

use App\Enums\OrderStatus;
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

    private function confirmedOrder(User $user, float $total = 50.00): Order
    {
        return Order::factory()->for($user)->create([
            'status' => OrderStatus::Confirmed,
            'total' => $total,
        ]);
    }

    // ############################### start process tests ################################
    public function test_cash_on_delivery_creates_a_pending_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrder($user, 42.00);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::CashOnDelivery->value,
            ])
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Payment processed successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'method' => PaymentMethod::CashOnDelivery->value,
                    'status' => PaymentStatus::Pending->value,
                    'amount' => '42.00',
                ],
            ])
            ->assertJsonPath('data.transaction_reference', null);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_stripe_marks_the_payment_successful_with_a_reference(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrder($user, 99.99);

        $response = $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'method' => PaymentMethod::Stripe->value,
                    'status' => PaymentStatus::Successful->value,
                    'amount' => '99.99',
                ],
            ]);

        $this->assertStringStartsWith('pi_', $response->json('data.transaction_reference'));
        $this->assertNotNull($response->json('data.paid_at'));
    }

    public function test_payment_amount_matches_the_order_total(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrder($user, 123.45);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '123.45');
    }

    public function test_only_confirmed_orders_can_be_paid(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_order_can_only_be_paid_once(): void
    {
        $user = User::factory()->create();
        $order = $this->confirmedOrder($user);
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
        $user = User::factory()->create();
        $order = $this->confirmedOrder($user);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), ['payment_method' => 'bitcoin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_payment_is_forbidden_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

        $this->actingAsUser($user)
            ->postJson(route('orders.payment.store', $order), [
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_requires_authentication(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

        $this->postJson(route('orders.payment.store', $order), [
            'payment_method' => PaymentMethod::Stripe->value,
        ])->assertUnauthorized();
    }

    // ############################### end process tests ################################

    // ############################### start view tests ################################
    public function test_index_lists_only_the_users_payments(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(2)->for($user)->has(Payment::factory(), 'payment')->create();
        Order::factory()->count(3)->for($other)->has(Payment::factory(), 'payment')->create();

        $this->actingAsUser($user)
            ->getJson(route('payments.index'))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Payments retrieved successfully.'])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson(route('payments.index'))->assertUnauthorized();
    }

    public function test_show_returns_the_payment_for_an_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        $payment = Payment::factory()->for($order)->create(['method' => PaymentMethod::Stripe]);

        $this->actingAsUser($user)
            ->getJson(route('orders.payment.show', $order))
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.method', PaymentMethod::Stripe->value);
    }

    public function test_show_is_not_found_when_the_order_has_no_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.payment.show', $order))
            ->assertNotFound();
    }

    public function test_show_forbids_viewing_another_users_order_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
        Payment::factory()->for($order)->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.payment.show', $order))
            ->assertForbidden();
    }

    // ############################### end view tests ################################
}
