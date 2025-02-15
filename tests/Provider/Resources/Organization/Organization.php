<?php

namespace Brahmic\ClientDTO\Test\Provider\Resources\Organization;

use Brahmic\ClientDTO\Contracts\AbstractResource;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Requests\GetPersonUuid;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Support\PersonalData;


class Organization extends AbstractResource
{

    /**
     * Создание запроса на проверку физического лица
     */
    public function createUuid(?PersonalData $personalData = null): GetPersonUuid
    {
        return $personalData ? GetPersonUuid::from($personalData) : new GetPersonUuid();
    }
}
