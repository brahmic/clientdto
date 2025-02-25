<?php

namespace Brahmic\ClientDTO\Test\Provider\Resources\Person\Requests;

use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\DTO\UUID;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\IP;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StartsWith;


class GetPersonUuid extends GetRequest
{
    public const string NAME = 'Получение идентификатора проверки физического лица';

    public const string DTO = UUID::class;

    public const string URI = 'org-check.json';

    //public const int ATTEMPTS = 3;


    //#[HideFromBody]
    public array $regions;

    #[MapOutputName('PeopleQuery.LastName')]
    public string $lastName;

    #[MapOutputName('PeopleQuery.FirstName')]
    public string $firstName;

    #[MapOutputName('PeopleQuery.SecondName')]
    public ?string $secondName = null;

    #[MapOutputName('PeopleQuery.BirthDate')]
    // добавить каст из строки-в строку?
    public ?Carbon $birthDate = null;

    #[MapOutputName('PeopleQuery.PassportSeries')]
    public ?string $passportSerial = null;

    #[MapOutputName('PeopleQuery.PassportNumber')]
    public ?string $passportNumber = null;

    #[MapOutputName('PeopleQuery.INN')]
    public ?string $inn = null;



    public function set(?array $regions = null, ?string $lastName = null, ?string $firstName = null): self
    {
        $this->appendQueryParam('num', '1');
        $this->appendQueryParam('num', '2');
        $this->appendQueryParam('num', '3');
        $this->addQueryParam('add', 'ADD');
        $this->attachQueryParam('sdsd', 'value1');
        $this->attachQueryParam('sdsd', 'value2');
        $this->attachQueryParam('sdsd', 'value3');

        return $this->fill(get_defined_vars(), filter: true);
    }


    protected function _queryParams(): array
    {
        return [
            're11gions' => $this->regions,
            'PeopleQuery.LastName' => $this->lastName,
            'PeopleQuery.FirstName' => $this->firstName,
            'PeopleQuery.SecondName' => $this->secondName,
//            'PeopleQuery.BirthDate' => $this->birthDate,
//            'PeopleQuery.PassportSeries' => $this->passportSerial,
//            'PeopleQuery.PassportNumber' => $this->passportNumber,
//            'PeopleQuery.INN' => $this->inn,
        ];
    }

    protected function _bodyParams(): array
    {
        return [
            'regions' => $this->regions,
            'PeopleQuery.LastName' => $this->lastName,
            'PeopleQuery.FirstName' => $this->firstName,
            'PeopleQuery.SecondName' => $this->secondName,
            'PeopleQuery.INN' => $this->inn,
        ];
    }


}
