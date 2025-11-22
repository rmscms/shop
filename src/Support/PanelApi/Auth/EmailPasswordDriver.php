<?php

namespace RMS\Shop\Support\PanelApi\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RMS\Shop\Contracts\PanelApi\AuthDriver;

class EmailPasswordDriver implements AuthDriver
{
    public function __construct(
        protected ?string $userModel = null
    ) {
        $this->userModel = $userModel ?: config('shop.panel_api.auth.user_model') ?: config('auth.providers.users.model');
    }

    public function handle(Request $request): PanelAuthResult
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:4'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        /** @var Authenticatable|Model|null $user */
        $user = $this->newUserQuery()->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        return new PanelAuthResult($user, [
            'device_name' => $credentials['device_name'] ?? null,
        ]);
    }

    protected function newUserQuery(): Builder
    {
        $model = $this->userModel;
        if (!class_exists($model)) {
            throw new \RuntimeException("Panel API auth user model [{$model}] not found.");
        }

        /** @var Model $instance */
        $instance = new $model();

        return $instance->newQuery();
    }
}

