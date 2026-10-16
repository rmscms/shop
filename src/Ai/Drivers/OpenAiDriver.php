<?php

namespace RMS\Shop\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use RMS\Shop\Ai\AiException;
use RMS\Shop\Ai\AiResult;
use RMS\Shop\Ai\JsonText;
use RMS\Shop\Contracts\Ai\AiDriver;

/**
 * Works with OpenAI and any endpoint that speaks its chat completions API.
 */
class OpenAiDriver implements AiDriver
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
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$image->mimeType.';base64,'.$image->base64],
            ];
        }

        $response = Http::withToken((string) $this->config['api_key'])
            ->timeout((int) ($this->config['timeout'] ?? 120))
            ->acceptJson()
            ->post(rtrim((string) $this->config['base_url'], '/').'/chat/completions', [
                'model' => (string) $this->config['model'],
                'max_tokens' => (int) ($this->config['max_tokens'] ?? 4096),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $content],
                ],
                'tools' => [[
                    'type' => 'function',
                    'function' => [
                        'name' => self::TOOL,
                        'description' => 'Submit the product suggestion.',
                        'parameters' => $schema,
                    ],
                ]],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => self::TOOL]],
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new AiException('OpenAI '.$response->status().': '.mb_substr((string) $message, 0, 500));
        }

        $raw = $response->json('choices.0.message.tool_calls.0.function.arguments')
            ?? $response->json('choices.0.message.content');
        // Compatible endpoints without tool support may answer with plain JSON text.
        $data = is_string($raw) ? JsonText::decode($raw) : null;

        if (!is_array($data)) {
            throw new AiException('پاسخ OpenAI قابل خواندن نبود (finish_reason: '.$response->json('choices.0.finish_reason').').');
        }

        return new AiResult(
            data: $data,
            model: (string) ($response->json('model') ?? $this->config['model']),
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
        );
    }
}
