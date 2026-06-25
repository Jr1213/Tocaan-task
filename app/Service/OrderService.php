<?php

namespace App\Service;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderService
{
    /**
     * Paginate the orders belonging to a given user.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::whereUserId($userId)
            ->with('items')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Step 1 — create the order shell for the given user.
     */
    public function createOrder(int $userId): Order
    {
        return Order::create([
            'user_id' => $userId,
            'status'  => OrderStatus::Pending,
            'total'   => 0,
        ]);
    }

    /**
     * Step 2 — add a line item per requested product, priced from the product.
     *
     * Stock is not touched here; it is reserved when the order is updated.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function addItems(Order $order, array $items): void
    {
        foreach ($items as $line) {
            $product = Product::findOrFail($line['product_id']);

            $order->items()->create([
                'product_id'  => $product->id,
                'quantity'    => $line['quantity'],
                'unit_price'  => $product->price,
                'total_price' => $line['quantity'] * $product->price,
            ]);
        }
    }

    /**
     * Step 3 — sum the line items and store the order total.
     */
    public function sumTotal(Order $order): void
    {
        $order->update(['total' => $order->items()->sum('total_price')]);
    }

    /**
     * Ensure every product on the order has enough stock to fulfil it.
     *
     * Each product row is locked for the duration of the transaction so the
     * stock checked here cannot be taken by a concurrent request before it is
     * decremented.
     */
    public function assertStockAvailable(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = Product::lockForUpdate()->findOrFail($item->product_id);

            $this->guardAvailableStock($product, $item->quantity);
        }
    }

    /**
     * Decrement the available stock for each of the order's line items.
     */
    public function decrementStock(Order $order): void
    {
        foreach ($order->items as $item) {
            Product::whereKey($item->product_id)->decrement('quantity', $item->quantity);
        }
    }

    /**
     * Return the order's reserved stock back to each product.
     */
    public function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            Product::whereKey($item->product_id)->increment('quantity', $item->quantity);
        }
    }

    /**
     * Update an existing order and return it with its line items.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order->load('items');
    }

    /**
     * Delete an order and its line items.
     */
    public function delete(Order $order): void
    {
        $order->delete();
    }

    /**
     * Ensure a product has enough stock to satisfy the requested quantity.
     */
    private function guardAvailableStock(Product $product, int $quantity): void
    {
        if ($product->quantity < $quantity) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "Insufficient stock for product '{$product->name}'. Available: {$product->quantity}, requested: {$quantity}.",
            );
        }
    }
}
