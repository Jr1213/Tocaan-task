<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Service\PaymentService;

class PayOrderAction
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * Pay for an order with the chosen payment method.
     */
    public function execute(Order $order, PaymentMethod $method): Payment
    {
        return $this->paymentService->pay($order, $method);
    }
}
