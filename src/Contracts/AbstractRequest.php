<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\PropertyContext;
use Brahmic\ClientDTO\Support\RequestHelper;
use Brahmic\ClientDTO\Traits\BodyFormat;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Timeout;
use Exception;
use Spatie\LaravelData\Data;


abstract class AbstractRequest extends Data implements ClientRequestInterface
{
    use QueryParams, Timeout, BodyFormat;

    public const int ATTEMPTS = 1;

    public const int ATTEMPT_DELAY = 1000;

    public const ?string URI = null;

    public const ?string DTO = null;

    public const string NAME = 'Абстрактный запрос';

    //public const string REQUEST_OPTIONS = RequestOptions::JSON;

    private ?ClientDTO $clientDTO = null;

    private ?AbstractResource $resource = null;

    // todo попытки, если валидация ответа не прошла и клиент решил повторить запрос

    public function send(): ClientResponseInterface|ClientResponse
    {
        $executiveRequest = new ExecutiveRequest($this);

        //https://irbis.plus/ru/base/-/services/
        //people-check.json?uuid=d9d27097-0689-4dfa-a819-71671ec971a6&filter0=allData&version=2&strategy=selected+&page=1&rows=10&token=45fadabbd2113da853324e3b6c8b4927
        //https://irep.bezopasno.org/ru/base/-/services/report/d9d27097-0689-4dfa-a819-71671ec971a6/
        //people-judge.json?event=role-data&page=1&rows=10&filter0=allData&filter_text=&strategy=selected&version=2&has_resolution=false

        $clientResponse = $executiveRequest->send();

        while ($executiveRequest->canAttempt() && $clientResponse->isAttemptNeeded()) {
            $clientResponse = $executiveRequest->send();
        }

        return $clientResponse;
    }


    /**
     * Определяет нужна ли дополнительная попытка
     * Default behavior
     * @return bool
     */
    public function conditionOfAttempt(): bool
    {
        return false;
    }

    /**
     * Определяет условие, при котором задача по получению данных выполнена.
     * Вызывается только для запросов 2xx.
     * @return bool
     */
    public function conditionIfResolved(): bool
    {
        return false;
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

    public function getAttempts(): int
    {
        return static::ATTEMPTS;
    }

    public function getAttemptDelay(): int
    {
        return static::ATTEMPT_DELAY;
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
