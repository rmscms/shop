<?php

namespace RMS\Shop\Policies;

use RMS\Shop\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return auth()->check();
    }

    public function view(?User $user, Product $product): bool
    {
        return auth()->check();
    }

    public function create(?User $user): bool
    {
        return auth()->check();
    }

    public function update(?User $user, Product $product): bool
    {
        return auth()->check();
    }

    public function delete(?User $user, Product $product): bool
    {
        return auth()->check();
    }

    public function manageImages(?User $user, Product $product): bool
    {
        return auth()->check();
    }

    public function manageVideos(?User $user, Product $product): bool
    {
        return auth()->check();
    }

    public function manageCombinations(?User $user, Product $product): bool
    {
        return auth()->check();
    }
}


