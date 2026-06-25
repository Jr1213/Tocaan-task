<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Service\OrderService;
use Illuminate\Support\Facades\DB;

class UpdateOrderAction
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Update an order, adjusting product stock based on the target status:
     *
     *  - pending -> completed: check availability first, then reserve stock.
     *  - completed -> cancelled: release the reserved stock back to products.
     *
     * Everything runs in one transaction, so if availability fails the status
     * change rolls back with it.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order->loadMissing('items');

            $current = $order->status;
            $target  = OrderStatus::from($data['status']);

            if ($target === OrderStatus::Completed && $current === OrderStatus::Pending) {
                $this->orderService->assertStockAvailable($order);
                $this->orderService->decrementStock($order);
            }

            if ($target === OrderStatus::Cancelled && $current === OrderStatus::Completed) {
                $this->orderService->restoreStock($order);
            }

            return $this->orderService->update($order, $data);
        });
    }
}
