<?php

namespace Brahmic\ClientDTO\Traits;

trait Headers
{
    private array $headers = [];

    public function removeHeader(string $key): static
    {
        if (isset($this->headers[$key])) {
            unset($this->headers[$key]);
        }

        return $this;
    }

    public function addHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }


    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }
}