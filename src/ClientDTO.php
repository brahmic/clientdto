<?php

namespace Brahmic\ClientDTO;

use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Traits\CustomQueryParams;
use Brahmic\ClientDTO\Traits\Headers;

class ClientDTO
{
    use CustomQueryParams, Headers;

    private ?string $baseUrl = null;

    private int $timeout = 60;

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

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
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

        $this->baseUrl = $baseUrl;

        return $this;
    }


}
