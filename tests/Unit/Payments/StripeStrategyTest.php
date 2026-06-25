<?php

namespace Tests\Unit\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\StripeStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_payment_successful_with_a_reference(): void
    {
        config(['services.stripe.secret' => 'sk_test_example']);

        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

        (new StripeStrategy)->pay($payment);

        $fresh = $payment->fresh();
        $this->assertSame(PaymentStatus::Successful, $fresh->status);
        $this->assertStringStartsWith('pi_', $fresh->transaction_reference);
        $this->assertNotNull($fresh->paid_at);
    }
}
