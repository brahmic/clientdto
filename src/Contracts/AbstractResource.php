<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;

abstract class AbstractResource
{

    public function __construct(private readonly ClientDTO $dataProvider)
    {

    }

    public function getDataProvider(): ClientDTO
    {
        return $this->dataProvider;
    }

}
