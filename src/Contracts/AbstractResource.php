<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\RemoteResourceProvider;

abstract class AbstractResource
{

    public function __construct(private readonly RemoteResourceProvider $dataProvider)
    {

    }

    public function getDataProvider(): RemoteResourceProvider
    {
        return $this->dataProvider;
    }

}
