<?php

namespace Brahmic\ClientDTO\Test\Provider\Resources\Person\Requests;

use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\DTO\UUID;
use Brahmic\ClientDTO\Test\Provider\Resources\Person\Support\PersonalData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MapOutputName;

class GetUuid extends GetRequest
{
    public const string NAME = 'Получение идентификатора проверки физического лица';
    public const string DTO = UUID::class;

    public const string URI = 'org-check.json';

    #[MapOutputName('PeopleQuery.Regions')]
    public array $regions;

    #[MapOutputName('PeopleQuery.LastName')]
    public string $lastName;

    #[MapOutputName('PeopleQuery.FirstName')]
    public string $firstName;

    #[MapOutputName('PeopleQuery.SecondName')]
    public ?string $secondName = null;

    #[MapOutputName('PeopleQuery.BirthDate')]
    public ?Carbon $birthDate = null;

    #[MapOutputName('PeopleQuery.PassportSeries')]
    public ?string $passportSerial = null;

    #[MapOutputName('PeopleQuery.PassportNumber')]
    public ?string $passportNumber = null;

    #[MapOutputName('PeopleQuery.INN')]
    public ?string $inn = null;

    //protected array $required = ['address'];
    //protected array $payload = [];


    public function set(?array $regions = null, ?string $lastName = null, ?string $firstName = null): self
    {
        $this->appendQueryParam('num', '1');
        $this->appendQueryParam('num', '2');
        $this->appendQueryParam('num', '3');
        $this->addQueryParam('add', 'ADD');
        $this->attachQueryParam('sdsd', 'value1');
        $this->attachQueryParam('sdsd', 'value2');
        $this->attachQueryParam('sdsd', 'value3');

        return $this->setParticular(get_defined_vars());
    }

    /**
     * Метод переопределён только ради подсказки PersonalData
     *
     * @param PersonalData|array|Arrayable $data
     * @return $this
     */
    public function setFrom(PersonalData|array|Arrayable $data): static
    {
        return parent::setFrom($data);
    }

    protected function customQueryParams(): array
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

//    protected function bodyParams(): array
//    {
//        return [
//            'regions' => $this->regions,
//            'PeopleQuery.LastName' => $this->lastName,
//            'PeopleQuery.FirstName' => $this->firstName,
//            'PeopleQuery.SecondName' => $this->secondName,
//            'PeopleQuery.INN' => $this->inn,
//        ];
//    }


}
