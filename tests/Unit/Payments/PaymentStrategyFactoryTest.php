<?php

namespace Tests\Unit\Payments;

use App\Enums\PaymentMethod;
use App\Payments\CashOnDeliveryStrategy;
use App\Payments\PaymentStrategyFactory;
use App\Payments\StripeStrategy;
use Tests\TestCase;

class PaymentStrategyFactoryTest extends TestCase
{
    private function factory(): PaymentStrategyFactory
    {
        return new PaymentStrategyFactory;
    }

    public function test_it_resolves_the_cash_on_delivery_strategy(): void
    {
        $this->assertInstanceOf(
            CashOnDeliveryStrategy::class,
            $this->factory()->make(PaymentMethod::CashOnDelivery),
        );
    }

    public function test_it_resolves_the_stripe_strategy(): void
    {
        $this->assertInstanceOf(
            StripeStrategy::class,
            $this->factory()->make(PaymentMethod::Stripe),
        );
    }
}
