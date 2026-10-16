<?php

namespace RMS\Shop\Ai;

use RMS\Shop\Ai\Drivers\AnthropicDriver;
use RMS\Shop\Ai\Drivers\GeminiDriver;
use RMS\Shop\Ai\Drivers\OpenAiDriver;
use RMS\Shop\Contracts\Ai\AiDriver;

class AiManager
{
    public function driverName(): string
    {
        return (string) config('shop.ai.driver', 'anthropic');
    }

    /**
     * @param  array<string, mixed>  $overrides  Per-call options such as a larger max_tokens
     */
    public function driver(?string $name = null, array $overrides = []): AiDriver
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

        $config = $overrides + $config + $options;

        return match ($name) {
            'anthropic' => new AnthropicDriver($config),
            'openai' => new OpenAiDriver($config),
            'gemini' => new GeminiDriver($config),
            default => throw new AiException("درایور AI ناشناخته: {$name}"),
        };
    }

    /**
     * Whether the active driver can search the web to check specs.
     */
    public function searchEnabled(): bool
    {
        return $this->driverName() === 'gemini' && (bool) config('shop.ai.drivers.gemini.search', false);
    }

    /**
     * Seconds to wait between queued jobs, so free tiers stay under their rate limit.
     */
    public function dispatchDelay(int $index): int
    {
        return max(0, (int) config('shop.ai.dispatch_spacing', 0)) * $index;
    }

    public function configured(): bool
    {
        return (bool) config('shop.ai.enabled', true)
            && !empty(config('shop.ai.drivers.'.$this->driverName().'.api_key'));
    }
}
