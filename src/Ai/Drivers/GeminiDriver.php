<?php

namespace RMS\Shop\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use RMS\Shop\Ai\AiException;
use RMS\Shop\Ai\AiResult;
use RMS\Shop\Ai\JsonText;
use RMS\Shop\Contracts\Ai\AiDriver;

/**
 * Google Gemini with optional Google Search grounding, so specs can be
 * checked against manufacturer pages and datasheets.
 */
class GeminiDriver implements AiDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config)
    {
    }

    public function generate(string $system, string $prompt, array $images, array $schema): AiResult
    {
        $search = (bool) ($this->config['search'] ?? false);

        $generationConfig = ['maxOutputTokens' => (int) ($this->config['max_tokens'] ?? 4096)];
        if ($search) {
            // Search and a response schema don't reliably work together, so the schema goes in the prompt.
            $prompt .= "\n\nAnswer with only one JSON object (no prose, no code fence) that matches this JSON schema:\n"
                .json_encode($schema, JSON_UNESCAPED_UNICODE);
        } else {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseJsonSchema'] = $schema;
        }

        $parts = [];
        foreach ($images as $image) {
            $parts[] = ['inline_data' => ['mime_type' => $image->mimeType, 'data' => $image->base64]];
        }
        $parts[] = ['text' => $prompt];

        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => $generationConfig,
        ];
        if ($search) {
            $body['tools'] = [['google_search' => new \stdClass()]];
        }

        $model = (string) $this->config['model'];
        $response = Http::withHeaders(['x-goog-api-key' => (string) $this->config['api_key']])
            ->timeout((int) ($this->config['timeout'] ?? 120))
            ->acceptJson()
            ->post(rtrim((string) $this->config['base_url'], '/').'/models/'.$model.':generateContent', $body);

        if ($response->failed()) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            if (stripos($message, 'location is not supported') !== false) {
                throw new AiException('Gemini دسترسی از این موقعیت (IP ایران) را نمی‌دهد. درخواست را از سرور خارج بفرستید یا SHOP_AI_GEMINI_URL را روی یک پراکسی بگذارید.');
            }
            throw new AiException('Gemini '.$response->status().': '.mb_substr($message, 0, 500));
        }

        $text = '';
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            if (empty($part['thought']) && isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        $data = $text !== '' ? JsonText::decode($text) : null;
        if (!is_array($data)) {
            throw new AiException('پاسخ Gemini قابل خواندن نبود (finishReason: '
                .($response->json('candidates.0.finishReason') ?? $response->json('promptFeedback.blockReason')).').');
        }

        return new AiResult(
            data: $data,
            model: (string) ($response->json('modelVersion') ?? $model),
            inputTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0),
            sources: $this->sources((array) $response->json('candidates.0.groundingMetadata.groundingChunks', [])),
        );
    }

    /**
     * @param  array<int, mixed>  $chunks
     * @return array<int, array{title: string, url: string}>
     */
    protected function sources(array $chunks): array
    {
        $sources = [];
        foreach ($chunks as $chunk) {
            $url = (string) ($chunk['web']['uri'] ?? '');
            if ($url === '' || isset($sources[$url])) {
                continue;
            }
            $sources[$url] = ['title' => (string) ($chunk['web']['title'] ?? $url), 'url' => $url];
        }

        return array_values($sources);
    }
}
