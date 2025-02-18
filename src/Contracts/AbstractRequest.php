<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Builders\RequestBuilder;
use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Support\PropertyContext;
use Brahmic\ClientDTO\Support\RequestHelper;
use Brahmic\ClientDTO\Traits\BodyFormat;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Timeout;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;


abstract class AbstractRequest extends Data
{
    use QueryParams, Timeout, BodyFormat;

    public const ?string URI = null;

    public const ?string DTO = null;

    public const string NAME = 'Абстрактный запрос';

    //public const string REQUEST_OPTIONS = RequestOptions::JSON;

    private ?ClientDTO $clientDTO = null;

    private ?AbstractResource $resource = null;

    // todo попытки, если валидация ответа не прошла и клиент решил повторить запрос

    public function send()
    {
        $builder = new RequestBuilder($this);

        dump('AbstractRequest send');
        dump($builder);
        dump($builder->toArray());
        $response = $builder->send();
        dump('Response:');



        return $response;
    }

    /**
     * @throws \Exception
     */
    public static function getDtoClass(): string
    {
        if (static::DTO) {

            return static::DTO;
        }

        throw new Exception('Неизвестный тип запроса');
    }

    public function resolveDtoClass(): string
    {
        return $this->getDtoClass();
    }

    public function getUrl(): string
    {
        return $this->getBaseUrl($this->getUri());
    }

    public function getBaseUrl(?string $uri = ''): string
    {
        return $this->getClientDTO()->getBaseUrl($uri);
    }

    public static function getUri(): string
    {
        return static::URI;
    }

    public static function getName(): string
    {
        return static::NAME;
    }

    public function validator(): ClientDTO
    {
        return $this->getClientDTO()->validator();
    }
    public function getClientDTO(): ClientDTO
    {
        return $this->clientDTO = $this->clientDTO ?: ClientResolver::resolve(static::class);
    }

    public function getResource(): AbstractResource
    {
        return $this->resource = $this->resource ?: ClientResolver::resolveResource(static::class);
    }

    public function queryParams(): array
    {
        return RequestHelper::getInstance()->resolveRequestParams($this, PropertyContext::QueryString);
    }

    public function bodyParams(): array
    {
        return RequestHelper::getInstance()->resolveRequestParams($this, PropertyContext::Body);
    }

    public function fill(object|array $data, bool $filter = false): static
    {
        return RequestHelper::getInstance()->fill($this, $data, $filter);
    }
}
