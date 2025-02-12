<?php

namespace Brahmic\ClientDTO\Test\Provider\Resources\Person;

use Brahmic\ClientDTO\Contracts\AbstractResource;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Requests\GetUuid;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Support\PersonalData;


class Person extends AbstractResource
{

    /**
     * Создание запроса на проверку физического лица
     */
    public function createUuid(?PersonalData $personalData = null): GetUuid
    {
        return tap(new GetUuid()->setClientDTO($this->getClientDTO()), function (GetUuid $getUuid) use ($personalData) {
            if ($personalData !== null) {
                $getUuid->setFrom($personalData);
            }
        });
    }
}
