<?php

namespace Brahmic\ClientDTO;

use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Headers;
use Brahmic\ClientDTO\Traits\Timeout;

class ClientDTO
{
    use QueryParams, Headers, Timeout;

    private ?string $baseUrl = null;


    private bool $debug = false;


    private ?string $requestBodyType = null;    //one of RequestOptions


    /**
     * @param string $type
     * @return $this
     */
    public function setRequestBodyType(string $type): static
    {
        $this->requestBodyType = $type;

        return $this;
    }

    public function getRequestBodyType(): ?string
    {
        return $this->requestBodyType;
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


}
