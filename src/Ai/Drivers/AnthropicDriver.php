<?php

namespace RMS\Shop\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use RMS\Shop\Ai\AiException;
use RMS\Shop\Ai\AiResult;
use RMS\Shop\Contracts\Ai\AiDriver;

class AnthropicDriver implements AiDriver
{
    protected const TOOL = 'submit_result';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config)
    {
    }

    public function generate(string $system, string $prompt, array $images, array $schema): AiResult
    {
        $content = [];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image->mimeType,
                    'data' => $image->base64,
                ],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        // A forced tool call makes the model return arguments that follow the schema.
        $response = Http::withHeaders([
                'x-api-key' => (string) $this->config['api_key'],
                'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
            ])
            ->timeout((int) ($this->config['timeout'] ?? 120))
            ->acceptJson()
            ->post(rtrim((string) $this->config['base_url'], '/').'/messages', [
                'model' => (string) $this->config['model'],
                'max_tokens' => (int) ($this->config['max_tokens'] ?? 4096),
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
                'tools' => [[
                    'name' => self::TOOL,
                    'description' => 'Submit the product suggestion.',
                    'input_schema' => $schema,
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new AiException('Anthropic '.$response->status().': '.mb_substr((string) $message, 0, 500));
        }

        $data = null;
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL) {
                $data = $block['input'] ?? null;
                break;
            }
        }

        if (!is_array($data)) {
            throw new AiException('پاسخ Anthropic ساختار مورد انتظار را نداشت (stop_reason: '.$response->json('stop_reason').').');
        }

        return new AiResult(
            data: $data,
            model: (string) ($response->json('model') ?? $this->config['model']),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
        );
    }
}
