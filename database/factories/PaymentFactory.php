<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id'              => Order::factory(),
            'method'                => PaymentMethod::CashOnDelivery,
            'status'                => PaymentStatus::Pending,
            'amount'                => fake()->randomFloat(2, 1, 500),
            'transaction_reference' => null,
            'paid_at'               => null,
        ];
    }
}
