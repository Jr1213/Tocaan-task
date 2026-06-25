<?php

namespace Tests\Feature\API;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): static
    {
        $token = JWTAuth::fromUser($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * A valid store payload with two line items.
     * Total: (2 * 10.00) + (1 * 15.50) = 35.50.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                ['product_name' => 'Keyboard', 'quantity' => 2, 'price' => 10.00],
                ['product_name' => 'Mouse', 'quantity' => 1, 'price' => 15.50],
            ],
        ], $overrides);
    }

    // ############################### start index tests ################################
    public function test_index_returns_only_the_authenticated_users_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(2)->for($user)->create();
        Order::factory()->count(3)->for($other)->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.index'))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Orders retrieved successfully.'])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        Order::factory()->for($user)->create(['status' => OrderStatus::Confirmed]);
        Order::factory()->for($user)->create(['status' => OrderStatus::Confirmed]);

        $this->actingAsUser($user)
            ->getJson(route('orders.index', ['status' => 'confirmed']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_rejects_an_invalid_status_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.index', ['status' => 'bogus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson(route('orders.index'))->assertUnauthorized();
    }

    // ############################### end index tests ################################

    // ############################### start store tests ################################
    public function test_store_creates_order_with_items_and_computes_total(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), $this->validPayload())
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'user_id' => $user->id,
                    'status' => OrderStatus::Pending->value,
                    'total' => '35.50',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id', 'user_id', 'status', 'total',
                    'items' => [['id', 'product_name', 'quantity', 'price', 'subtotal']],
                ],
            ])
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => '35.50']);
        $this->assertDatabaseHas('order_items', [
            'product_name' => 'Keyboard',
            'quantity' => 2,
            'price' => '10.00',
        ]);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_store_uses_authenticated_user_not_request_input(): void
    {
        $user = User::factory()->create();
        $hacker = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), $this->validPayload(['user_id' => $hacker->id]))
            ->assertCreated()
            ->assertJson(['data' => ['user_id' => $user->id]]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson(route('orders.store'), $this->validPayload())->assertUnauthorized();
    }

    public function test_store_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_store_validates_item_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), [
                'items' => [['product_name' => '', 'quantity' => 0, 'price' => -1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.product_name',
                'items.0.quantity',
                'items.0.price',
            ]);
    }

    // ############################### end store tests ################################

    // ############################### start show tests ################################
    public function test_show_returns_the_order_with_its_items(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        OrderItem::factory()->count(2)->for($order)->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.show', $order))
            ->assertOk()
            ->assertJson(['data' => ['id' => $order->id]])
            ->assertJsonCount(2, 'data.items');
    }

    public function test_show_forbids_access_to_another_users_order(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.show', Order::factory()->create()))
            ->assertForbidden();
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson(route('orders.show', Order::factory()->create()))->assertUnauthorized();
    }

    // ############################### end show tests ################################

    // ############################### start update tests ################################
    public function test_update_changes_the_order_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Confirmed->value])
            ->assertOk()
            ->assertJson(['data' => ['status' => OrderStatus::Confirmed->value]]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Confirmed->value]);
    }

    public function test_update_can_replace_items_and_recompute_total(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), [
                'items' => [
                    ['product_name' => 'Monitor', 'quantity' => 2, 'price' => 100.00],
                ],
            ])
            ->assertOk()
            ->assertJson(['data' => ['total' => '200.00']])
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Monitor']);
    }

    public function test_update_rejects_an_invalid_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => 'not-a-status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_forbids_updating_another_users_order(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->putJson(route('orders.update', Order::factory()->create()), ['status' => OrderStatus::Confirmed->value])
            ->assertForbidden();
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson(route('orders.update', Order::factory()->create()), ['status' => OrderStatus::Confirmed->value])
            ->assertUnauthorized();
    }

    // ############################### end update tests ################################

    // ############################### start destroy tests ################################
    public function test_destroy_soft_deletes_an_order_without_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', $order))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Order deleted successfully.']);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_destroy_is_blocked_when_a_payment_is_associated(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Confirmed]);
        Payment::factory()->for($order)->create();

        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', $order))
            ->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_destroy_forbids_deleting_another_users_order(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', Order::factory()->create()))
            ->assertForbidden();
    }

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson(route('orders.destroy', Order::factory()->create()))->assertUnauthorized();
    }

    // ############################### end destroy tests ################################
}
