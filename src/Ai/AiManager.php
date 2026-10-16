<?php

namespace RMS\Shop\Ai;

use RMS\Shop\Ai\Drivers\AnthropicDriver;
use RMS\Shop\Ai\Drivers\OpenAiDriver;
use RMS\Shop\Contracts\Ai\AiDriver;

class AiManager
{
    public function driverName(): string
    {
        return (string) config('shop.ai.driver', 'anthropic');
    }

    public function driver(?string $name = null): AiDriver
    {
        $name ??= $this->driverName();
        $config = (array) config('shop.ai.drivers.'.$name, []);

        if (empty($config['api_key'])) {
            throw new AiException("کلید API برای درایور «{$name}» تنظیم نشده است.");
        }

        $options = [
            'max_tokens' => (int) config('shop.ai.max_tokens', 4096),
            'timeout' => (int) config('shop.ai.timeout', 120),
        ];

        return match ($name) {
            'anthropic' => new AnthropicDriver($config + $options),
            'openai' => new OpenAiDriver($config + $options),
            default => throw new AiException("درایور AI ناشناخته: {$name}"),
        };
    }

    public function configured(): bool
    {
        return (bool) config('shop.ai.enabled', true)
            && !empty(config('shop.ai.drivers.'.$this->driverName().'.api_key'));
    }
}
