<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\DataProviderClient;

abstract class AbstractResource
{

    public function __construct(private readonly DataProviderClient $client)
    {

    }

    public function getClient(): DataProviderClient
    {
        return $this->client;
    }

}
