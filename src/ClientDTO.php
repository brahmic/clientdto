<?php

namespace Brahmic\ClientDTO;

use Brahmic\ClientDTO\Contracts\ClientDTOInterface;
use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Traits\BodyFormat;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Headers;
use Brahmic\ClientDTO\Traits\Timeout;
use Illuminate\Http\Client\Response;
use Spatie\LaravelData\Data;

/**
 *
 */
class ClientDTO implements ClientDTOInterface
{
    use QueryParams, Headers, Timeout, BodyFormat;

    private ?string $baseUrl = null;


    private bool $debug = false;


    private array $logs = [];

    public function logs(): array
    {
        return $this->logs;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }


    public function getBaseUrl(?string $uri = ''): ?string
    {
        return $uri ? $this->baseUrl . $uri : $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        if (!$this->baseUrl) {
            ClientResolver::registerClient($this);
        }

        $this->setTimeout(60);

        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function advanceCreationDTO(array $data): ?Data
    {
        return null;
    }
}
