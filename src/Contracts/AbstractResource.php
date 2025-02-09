<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\DataProviderClient;

abstract class AbstractResource
{

    public function __construct(private readonly DataProviderClient $dataProvider)
    {

    }

    public function getDataProvider(): DataProviderClient
    {
        return $this->dataProvider;
    }

}
