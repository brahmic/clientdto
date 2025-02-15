<?php

namespace Brahmic\ClientDTO\Test\Provider;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Test\Provider\Resources\Organization\Organization;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Person;
use GuzzleHttp\RequestOptions;

class SomeDataClient extends ClientDTO
{

    // Системные запросы (получение uuid, прочее)
    public function __construct()
    {
        $this
            ->setBaseUrl('https://example.com/services/')
            ->setTimeout(60)
            ->setHeaders([])
            ->setDebug(true)
            ->setRequestBodyType(RequestOptions::MULTIPART)
            ->addQueryParam('token', '45fadabbd2113da853324e3b6c8b4927');
    }


    public function person(): Person
    {
        return new Person($this);
    }
    public function organization(): Organization
    {
        return new Organization($this);
    }
}
