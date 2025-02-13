<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;

abstract class AbstractResource
{

    public function __construct()
    {

    }

    public function getClientDTO(): ClientDTO
    {
        return $this->resolveClient(static::class);
    }

    private function resolveClient(string $class) :ClientDTO
    {
        return new $class();
    }

}
