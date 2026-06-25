<?php

namespace App\Actions;

use App\Models\Order;
use App\Service\OrderService;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Create an order by orchestrating the service steps: create the order,
     * add its line items, and sum the total. Stock is not reserved here — it
     * is decremented when the order is updated. The whole sequence runs in one
     * transaction so a partial order can't persist.
     *
     * @param  array{user_id: int, items: array<int, array{product_id: int, quantity: int}>}  $data
     */
    public function execute(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = $this->orderService->createOrder($data['user_id']);
            $this->orderService->addItems($order, $data['items']);
            $this->orderService->sumTotal($order);

            return $order->load('items');
        });
    }
}
