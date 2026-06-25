<?php

namespace Tests\Feature\API;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    ################################ start index tests ################################
    public function test_index_lists_products_without_authentication(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson(route('products.index'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Products retrieved successfully.',
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [['id', 'name', 'price', 'quantity', 'created_at', 'updated_at']],
            ]);
    }

    public function test_index_returns_empty_list_when_no_products_exist(): void
    {
        $this->getJson(route('products.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_excludes_soft_deleted_products(): void
    {
        Product::factory()->count(2)->create();
        Product::factory()->create()->delete();

        $this->getJson(route('products.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_does_not_embed_orders(): void
    {
        $product = Product::factory()->create();
        $order   = Order::factory()->for(User::factory())->create();
        OrderItem::factory()->for($order)->for($product)->create();

        $this->getJson(route('products.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.orders');
    }

    ################################ end index tests ################################

    ################################ start show tests ################################
    public function test_show_returns_product_with_all_its_orders_without_authentication(): void
    {
        $product = Product::factory()->create();
        $order   = Order::factory()->for(User::factory())->create();
        OrderItem::factory()->for($order)->for($product)->create();

        $this->getJson(route('products.show', $product))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Product retrieved successfully.',
                'data'    => ['id' => $product->id],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'price',
                    'quantity',
                    'orders' => [['id', 'user_id', 'status', 'total', 'items']],
                ],
            ])
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.id', $order->id);
    }

    public function test_show_returns_orders_from_multiple_buyers(): void
    {
        $product = Product::factory()->create();

        $orderA = Order::factory()->for(User::factory())->create();
        $orderB = Order::factory()->for(User::factory())->create();
        OrderItem::factory()->for($orderA)->for($product)->create();
        OrderItem::factory()->for($orderB)->for($product)->create();

        $this->getJson(route('products.show', $product))
            ->assertOk()
            ->assertJsonCount(2, 'data.orders');
    }

    public function test_show_does_not_duplicate_an_order_that_lists_the_product_twice(): void
    {
        $product = Product::factory()->create();
        $order   = Order::factory()->for(User::factory())->create();

        OrderItem::factory()->count(2)->for($order)->for($product)->create();

        $this->getJson(route('products.show', $product))
            ->assertOk()
            ->assertJsonCount(1, 'data.orders');
    }

    public function test_show_only_returns_orders_that_contain_the_product(): void
    {
        $product      = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $relevantOrder = Order::factory()->for(User::factory())->create();
        $otherOrder    = Order::factory()->for(User::factory())->create();
        OrderItem::factory()->for($relevantOrder)->for($product)->create();
        OrderItem::factory()->for($otherOrder)->for($otherProduct)->create();

        $this->getJson(route('products.show', $product))
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.id', $relevantOrder->id);
    }

    public function test_show_returns_empty_orders_for_a_product_with_none(): void
    {
        $product = Product::factory()->create();

        $this->getJson(route('products.show', $product))
            ->assertOk()
            ->assertJsonCount(0, 'data.orders');
    }

    public function test_show_returns_not_found_for_unknown_product(): void
    {
        $this->getJson(route('products.show', 999999))
            ->assertNotFound();
    }

    public function test_show_returns_not_found_for_soft_deleted_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->getJson(route('products.show', $product))
            ->assertNotFound();
    }

    ################################ end show tests ################################
}
