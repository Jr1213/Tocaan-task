<?php

namespace Tests\Feature\API;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticate the given user for the next request via a JWT bearer token.
     */
    private function actingAsUser(User $user): static
    {
        $token = JWTAuth::fromUser($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * A valid store payload referencing two freshly created products.
     *
     * Prices/quantities are fixed so totals are deterministic:
     * (2 * 10.00) + (1 * 15.50) = 35.50.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $keyboard = Product::factory()->create(['price' => 10.00, 'quantity' => 100]);
        $mouse    = Product::factory()->create(['price' => 15.50, 'quantity' => 100]);

        return array_merge([
            'items' => [
                ['product_id' => $keyboard->id, 'quantity' => 2],
                ['product_id' => $mouse->id, 'quantity' => 1],
            ],
        ], $overrides);
    }

    ################################ start index tests ################################
    public function test_index_returns_only_the_authenticated_users_orders(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(2)->for($user)->create();
        Order::factory()->count(3)->for($other)->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.index'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Orders retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson(route('orders.index'))
            ->assertUnauthorized();
    }

    ################################ end index tests ################################

    ################################ start store tests ################################
    public function test_store_creates_order_with_items_and_computes_totals(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson(route('orders.store'), $this->validPayload());

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => [
                    'user_id' => $user->id,
                    'status'  => OrderStatus::Pending->value,
                    'total'   => '35.50',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'status',
                    'total',
                    'items' => [['id', 'product_id', 'quantity', 'unit_price', 'total_price']],
                ],
            ])
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total'   => '35.50',
        ]);
        $this->assertDatabaseHas('order_items', [
            'quantity'    => 2,
            'unit_price'  => '10.00',
            'total_price' => '20.00',
        ]);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_store_associates_order_with_authenticated_user_not_request_input(): void
    {
        $user   = User::factory()->create();
        $hacker = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), $this->validPayload(['user_id' => $hacker->id]))
            ->assertCreated()
            ->assertJson(['data' => ['user_id' => $user->id]]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson(route('orders.store'), $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_store_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), ['items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_store_requires_valid_item_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), [
                'items' => [
                    ['product_id' => 999999, 'quantity' => 0],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.product_id',
                'items.0.quantity',
            ]);
    }

    public function test_store_does_not_change_product_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 5.00, 'quantity' => 10]);

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ])
            ->assertCreated()
            ->assertJson(['data' => ['total' => '15.00']]);

        // Stock is reserved on update, not on create.
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_store_allows_quantity_beyond_available_stock(): void
    {
        // Availability is only enforced at update time, so create succeeds.
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 2]);

        $this->actingAsUser($user)
            ->postJson(route('orders.store'), [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertCreated();

        $this->assertSame(2, $product->fresh()->quantity);
    }

    ################################ end store tests ################################

    ################################ start show tests ################################
    public function test_show_returns_the_order_with_its_items(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        OrderItem::factory()->count(2)->for($order)->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.show', $order))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Order retrieved successfully.',
                'data'    => ['id' => $order->id],
            ])
            ->assertJsonCount(2, 'data.items');
    }

    public function test_show_forbids_access_to_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAsUser($user)
            ->getJson(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_show_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->getJson(route('orders.show', $order))
            ->assertUnauthorized();
    }

    ################################ end show tests ################################

    ################################ start update tests ################################
    public function test_update_changes_the_order_status(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Paid->value])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Order updated successfully.',
                'data'    => ['status' => OrderStatus::Paid->value],
            ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
    }

    public function test_completing_a_pending_order_decrements_product_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Completed->value])
            ->assertOk();

        $this->assertSame(7, $product->fresh()->quantity);
    }

    public function test_completing_rejects_and_rolls_back_when_stock_is_unavailable(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 2]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 5]);

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Completed->value])
            ->assertUnprocessable();

        // Stock untouched and the status change rolled back.
        $this->assertSame(2, $product->fresh()->quantity);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_non_completing_status_change_does_not_touch_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Paid->value])
            ->assertOk();

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_cancelling_a_completed_order_restores_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        // Complete first (stock 10 -> 7), then cancel (stock 7 -> 10).
        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Completed->value])
            ->assertOk();
        $this->assertSame(7, $product->fresh()->quantity);

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Cancelled->value])
            ->assertOk();

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_cancelling_a_pending_order_does_not_change_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        // Nothing was reserved, so cancelling must not inflate stock.
        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Cancelled->value])
            ->assertOk();

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_update_rejects_an_invalid_status(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => 'not-a-status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_forbids_updating_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Paid->value])
            ->assertForbidden();
    }

    public function test_update_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->putJson(route('orders.update', $order), ['status' => OrderStatus::Paid->value])
            ->assertUnauthorized();
    }

    ################################ end update tests ################################

    ################################ start destroy tests ################################
    public function test_destroy_soft_deletes_the_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        OrderItem::factory()->count(2)->for($order)->create();

        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', $order))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Order deleted successfully.',
            ]);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_deleting_a_completed_order_restores_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        // Complete first so the stock is reserved (10 -> 7).
        $this->actingAsUser($user)
            ->putJson(route('orders.update', $order), ['status' => OrderStatus::Completed->value])
            ->assertOk();
        $this->assertSame(7, $product->fresh()->quantity);

        // Deleting the completed order returns the stock (7 -> 10).
        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', $order))
            ->assertOk();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_deleting_a_pending_order_does_not_change_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $order   = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);

        // No stock was reserved, so deleting must not inflate it.
        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', $order))
            ->assertOk();

        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_destroyed_order_is_hidden_from_listing_and_lookup(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAsUser($user)->deleteJson(route('orders.destroy', $order))->assertOk();

        $this->actingAsUser($user)
            ->getJson(route('orders.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAsUser($user)
            ->getJson(route('orders.show', $order))
            ->assertNotFound();
    }

    public function test_destroy_forbids_deleting_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAsUser($user)
            ->deleteJson(route('orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->deleteJson(route('orders.destroy', $order))
            ->assertUnauthorized();
    }

    ################################ end destroy tests ################################
}
