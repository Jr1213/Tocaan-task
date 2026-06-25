<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Service\OrderService;
use Illuminate\Support\Facades\DB;

class DeleteOrderAction
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Delete an order, returning any stock it had reserved.
     *
     * Stock is only reserved once an order is completed, so it is restored to
     * the products only when a completed order is deleted. Both steps run in
     * one transaction.
     */
    public function execute(Order $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->status === OrderStatus::Completed) {
                $order->loadMissing('items');
                $this->orderService->restoreStock($order);
            }

            $this->orderService->delete($order);
        });
    }
}
