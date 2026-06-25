<?php

namespace App\Service;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderService
{
    /**
     * Paginate the orders belonging to a user, optionally filtered by status.
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForUser(int $userId, ?OrderStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $userId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('items')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create the order shell for the given user.
     */
    public function createOrder(int $userId): Order
    {
        return Order::create([
            'user_id' => $userId,
            'status' => OrderStatus::Pending,
            'total' => 0,
        ]);
    }

    /**
     * Add the given line items (product name, quantity, price) to the order.
     *
     * @param  array<int, array{product_name: string, quantity: int, price: float|string}>  $items
     */
    public function addItems(Order $order, array $items): void
    {
        $order->items()->createMany($items);
    }

    /**
     * Replace all of the order's line items with the given set.
     *
     * @param  array<int, array{product_name: string, quantity: int, price: float|string}>  $items
     */
    public function replaceItems(Order $order, array $items): void
    {
        $order->items()->delete();
        $this->addItems($order, $items);
    }

    /**
     * Recalculate and store the order total from its line items.
     */
    public function sumTotal(Order $order): void
    {
        $total = $order->items()->get()->sum(fn (OrderItem $item) => $item->subtotal());

        $order->update(['total' => $total]);
    }

    /**
     * Update the order's own attributes (e.g. status) and return it with items.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order->load('items');
    }

    /**
     * Delete an order, but only when it has no associated payment.
     */
    public function delete(Order $order): void
    {
        if ($order->payment()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'This order cannot be deleted because it has an associated payment.',
            );
        }

        $order->delete();
    }
}
