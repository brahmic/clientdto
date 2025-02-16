<?php

namespace Brahmic\ClientDTO\Test\Provider\Resources\Organization\Requests;


use Bezopasno\IrbisClient\Resources\Person\DTO\UUID;
use Brahmic\ClientDTO\Requests\GetRequest;
use Spatie\LaravelData\Support\Validation\ValidationContext;


class GetOrganizationUuid extends GetRequest
{
    public const string NAME = 'Получение идентификатора проверки юридического лица';
    public const string DTO = UUID::class;
    public const string URI = 'org-check.json';


    public ?string $inn = null;

    public ?string $ogr = null;

    public function set(?array $inn = null, ?string $ogrn = null): self
    {
        return $this->fill(get_defined_vars());
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'inn' => ['sometimes', 'required_without_all:ogrn'],
            'ogrn' => ['sometimes', 'required_without_all:inn'],
        ];
    }

}
