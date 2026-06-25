<?php

namespace App\Actions;

use App\Models\Order;
use App\Service\OrderService;

class DeleteOrderAction
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Delete an order. The service rejects deletion when a payment is
     * associated with the order.
     */
    public function execute(Order $order): void
    {
        $this->orderService->delete($order);
    }
}
