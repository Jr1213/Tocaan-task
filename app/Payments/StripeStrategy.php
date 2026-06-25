<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Str;

class StripeStrategy implements PaymentStrategy
{
    /**
     * Charge the customer through Stripe and mark the payment as paid.
     *
     * This is a placeholder for a real Stripe API call; it records a
     * transaction reference and the time the charge succeeded.
     */
    public function pay(Payment $payment): void
    {
        $payment->update([
            'status'                => PaymentStatus::Paid,
            'transaction_reference' => 'pi_'.Str::random(24),
            'paid_at'               => now(),
        ]);
    }
}
