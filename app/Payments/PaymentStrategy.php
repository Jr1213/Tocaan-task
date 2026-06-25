<?php

namespace App\Payments;

use App\Models\Payment;

interface PaymentStrategy
{
    /**
     * Process the given payment, updating its status (and any gateway
     * details) according to the payment method's behaviour.
     */
    public function pay(Payment $payment): void;
}
