<?php

namespace Brahmic\ClientDTO\Test\Resources\Person;

use Brahmic\ClientDTO\Test\Resources\Person\Requests\GetUuid;
use Brahmic\ClientDTO\Test\Resources\Person\Support\PersonalData;
use Brahmic\ClientDTO\Contracts\AbstractResource;

class Person extends AbstractResource
{

    /**
     * Создание запроса на проверку физического лица
     */
    public function createUuid(?PersonalData $personalData = null): GetUuid
    {
        return tap(new GetUuid($this->getClient()), function (GetUuid $getUuid) use ($personalData) {
            if ($personalData !== null) {
                $getUuid->setFrom($personalData);
            }
        });
    }
}
