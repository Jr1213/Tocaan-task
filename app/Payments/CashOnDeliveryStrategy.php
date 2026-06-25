<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

class CashOnDeliveryStrategy implements PaymentStrategy
{
    /**
     * Cash is collected from the customer on delivery, so the payment stays
     * pending until then — nothing is charged up front.
     */
    public function pay(Payment $payment): void
    {
        $payment->update([
            'status' => PaymentStatus::Pending,
        ]);
    }
}
