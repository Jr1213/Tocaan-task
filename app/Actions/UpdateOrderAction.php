<?php

namespace App\Actions;

use App\Models\Order;
use App\Service\OrderService;
use Illuminate\Support\Facades\DB;

class UpdateOrderAction
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Update an order's details. When new items are supplied they replace the
     * existing ones and the total is recalculated; the status is applied when
     * provided. Everything runs in one transaction.
     *
     * @param  array{status?: string, items?: array<int, array{product_name: string, quantity: int, price: float|string}>}  $data
     */
    public function execute(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            if (isset($data['items'])) {
                $this->orderService->replaceItems($order, $data['items']);
                $this->orderService->sumTotal($order);
            }

            if (isset($data['status'])) {
                $this->orderService->update($order, ['status' => $data['status']]);
            }

            return $order->load('items');
        });
    }
}
