<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Str;

class StripeStrategy implements PaymentStrategy
{
    public function pay(Payment $payment): void
    {
        $secret = (string) config('services.stripe.secret');

        $payment->update([
            'status' => PaymentStatus::Successful,
            'transaction_reference' => 'pi_'.substr(md5($secret), 0, 6).Str::random(18),
            'paid_at' => now(),
        ]);
    }
}
