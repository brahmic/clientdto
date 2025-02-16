<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Traits\QueryParams;

abstract class AbstractResource
{
    use QueryParams;

    private ?ClientDTO $clientDTO = null;

    public function getClientDTO(): ClientDTO
    {
        return $this->clientDTO = $this->clientDTO ?: ClientResolver::resolve(static::class);

    }
}
