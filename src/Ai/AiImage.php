<?php

namespace RMS\Shop\Ai;

final class AiImage
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $base64,
    ) {
    }
}
