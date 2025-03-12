<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Requests\ResponseResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Support\PropertyContext;
use Brahmic\ClientDTO\Support\RequestHelper;
use Brahmic\ClientDTO\Traits\BodyFormat;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Timeout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Illuminate\Contracts\Support\Arrayable;


abstract class AbstractRequest extends Data implements ClientRequestInterface, ChainInterface
{
    use QueryParams, Timeout, BodyFormat;

    public const int ATTEMPTS = 1;

    public const int ATTEMPT_DELAY = 1000;

    public const ?string URI = null;

    //public const ?string DTO = null;

    protected ?string $dto = null;

    public const string NAME = 'Абстрактный запрос';

    //public const string REQUEST_OPTIONS = RequestOptions::JSON;

    private ?ClientDTO $clientDTO = null;

    private ?AbstractResource $resource = null;
    private bool $hasBeenExecuted = false;
    private ?ClientResponseInterface $response = null;


    public function send(): ClientResponseInterface|ClientResponse
    {
        $this->hasBeenExecuted = true;
        $this->response = new ResponseResolver()->execute($this);
        return $this->response;
    }

    public function hasBeenExecuted(): bool
    {
        return $this->hasBeenExecuted;
    }

    public function resolveDtoClass(): null|string|Data
    {
        return $this->dto;
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

    public function getClientDTO(): ClientDTO
    {
        return $this->clientDTO = $this->clientDTO ?: ClientResolver::resolve(static::class);
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

    public function validateRequest(): array|Arrayable
    {
        $validator = Validator::make($this->toArray(), static::getValidationRules([]));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }


    /**
     * @return string
     * @throws \Exception
     */
    public function getMethod(): string
    {
        return match (true) {
            is_subclass_of($this, GetRequest::class) => 'get',
            is_subclass_of($this, PostRequest::class) => 'post',
            default => throw new \Exception('Unknown request type'),
        };
    }

    public function getResponseClass(): string
    {
        return $this->getClientDTO()->getResponseClass();
    }

    public function getResponse(): ?ClientResponseInterface
    {
        return $this->response;
    }

    /**
     * *** Attention! ***
     *
     * Now if DTO is specified, it is assumed that the server response is expected in JSON.
     * It is possible that in this case a different response may be expected,
     * which should be converted into a DTO object. In this case, the verification
     * method should be reviewed.
     *
     * Alternatively, this can be implemented by some kind of declaration,
     * in case the DTO is specified, but the data is expected in text form, for example.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        return !is_null($this->resolveDtoClass());
    }

    public function isDebug(): bool
    {
        return $this->getClientDTO()->isDebug();
    }

    /**
     * @return Collection<ChainInterface>
     */
    public function getChain(): Collection
    {
        return ClientResolver::getChain($this)->push($this);
    }


}
