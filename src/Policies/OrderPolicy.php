<?php

namespace RMS\Shop\Policies;

use App\Models\User;
use RMS\Shop\Models\Order;

class OrderPolicy
{
    public function view(?User $user, Order $order): bool
    {
        return $user && (int)$order->user_id === (int)$user->id;
    }

    public function viewAny(?User $user): bool
    {
        return (bool)$user;
    }
}


