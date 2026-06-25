<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    /**
     * An order may only be accessed by the user that owns it.
     */
    private function owns(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}
