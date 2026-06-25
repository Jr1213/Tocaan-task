<?php

namespace Tests\Unit\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\CashOnDeliveryStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashOnDeliveryStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_leaves_the_payment_pending_with_no_reference(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Failed]);

        (new CashOnDeliveryStrategy)->pay($payment);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->transaction_reference);
        $this->assertNull($payment->fresh()->paid_at);
    }
}
