<?php

namespace App\Payments;

use App\Enums\PaymentMethod;

class PaymentStrategyFactory
{
    /**
     * Resolve the payment strategy for the given method.
     *
     * Strategies are resolved from the container so they can declare their own
     * dependencies (e.g. a real Stripe client) as the implementations grow.
     */
    public function make(PaymentMethod $method): PaymentStrategy
    {
        return match ($method) {
            PaymentMethod::CashOnDelivery => app(CashOnDeliveryStrategy::class),
            PaymentMethod::Stripe         => app(StripeStrategy::class),
        };
    }
}
