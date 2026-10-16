<?php

namespace RMS\Shop\Ai;

final class AiResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{title: string, url: string}>  $sources  Web pages the model searched
     */
    public function __construct(
        public readonly array $data,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly array $sources = [],
    ) {
    }
}
