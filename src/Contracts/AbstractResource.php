<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;

abstract class AbstractResource
{

    public function __construct(private readonly ClientDTO $clientDTO)
    {

    }

    public function getClientDTO(): ClientDTO
    {
        return $this->clientDTO;
    }

}
