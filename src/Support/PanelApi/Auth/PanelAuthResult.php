<?php

namespace RMS\Shop\Support\PanelApi\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class PanelAuthResult
{
    public function __construct(
        protected Authenticatable $user,
        protected array $context = []
    ) {
    }

    public function user(): Authenticatable
    {
        return $this->user;
    }

    public function context(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->context;
        }

        return $this->context[$key] ?? $default;
    }
}

