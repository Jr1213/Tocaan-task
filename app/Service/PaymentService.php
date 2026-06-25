<?php

namespace App\Service;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentStrategyFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(private PaymentStrategyFactory $strategyFactory) {}

    /**
     * Pay for an order using the given method.
     *
     * Each order may only be paid once. A pending payment is created for the
     * order total, then the method's strategy processes it.
     */
    public function pay(Order $order, PaymentMethod $method): Payment
    {
        if ($order->payment()->exists()) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'This order has already been paid.',
            );
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'method'   => $method,
            'status'   => PaymentStatus::Pending,
            'amount'   => $order->total,
        ]);

        $this->strategyFactory->make($method)->pay($payment);

        return $payment->refresh();
    }
}
