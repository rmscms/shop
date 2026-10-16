<?php

namespace RMS\Shop\Contracts\Ai;

use RMS\Shop\Ai\AiImage;
use RMS\Shop\Ai\AiResult;

interface AiDriver
{
    /**
     * Ask the model for one JSON object matching $schema.
     *
     * @param  array<int, AiImage>  $images
     * @param  array<string, mixed>  $schema  JSON schema of the expected object
     */
    public function generate(string $system, string $prompt, array $images, array $schema): AiResult;
}
