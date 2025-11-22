<?php

namespace RMS\Shop\Support\PanelApi;

class PanelApiResponsePayload
{
    protected array $payload = [];

    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    public function set(string $key, mixed $value): self
    {
        $this->payload[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function merge(array $items): self
    {
        $this->payload = array_merge($this->payload, $items);

        return $this;
    }

    public function replace(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function all(): array
    {
        return $this->payload;
    }
}

