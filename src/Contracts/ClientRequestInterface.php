<?php

namespace Brahmic\ClientDTO\Contracts;


use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Support\Data;

interface ClientRequestInterface
{
    public function resolveDtoClass(): null|string|Data;

    public function toArray(): array;

    public function send(): ClientResponseInterface|ClientResponse;
}
