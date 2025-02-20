<?php

namespace Brahmic\ClientDTO\Test\Provider;

use Brahmic\ClientDTO\ClientDTO;
use Spatie\LaravelData\Data;
use Brahmic\ClientDTO\Test\Provider\Resources\Organization\Organization;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Person;
use GuzzleHttp\RequestOptions;

class SomeDataClient extends ClientDTO
{

    // Системные запросы (получение uuid, прочее)
    public function __construct()
    {

        $this
            ->setBaseUrl(env('CLIENTDTO_URL'))
            ->setTimeout(60)
            ->setHeaders([])
            ->setDebug(true)
            ->setBodyFormat(RequestOptions::MULTIPART)
            ->addQueryParam('token', env('CLIENTDTO_TOKEN'));
    }


    public function person(): Person
    {
        return new Person($this);
    }
    public function organization(): Organization
    {
        return new Organization($this);
    }

    public function advanceCreationDTO(array $responseData): ?Data
    {
        return ResponseDTO::from($responseData);
    }

    public function isAttemptNeeded(array $responseDTO): bool
    {
        return $responseDTO['status'] === -100;
    }
}
