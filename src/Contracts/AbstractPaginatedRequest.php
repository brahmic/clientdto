<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Requests\ResponseResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

abstract class AbstractPaginatedRequest extends Data
{
    protected ?AbstractRequest $clientRequest = null;

    /** @var string<PaginableInterface>  */
    protected string $requestClass;

    private ?int $statusCode = null;

    protected ?Collection $collection = null;

    public function send(): ClientResponse|ClientResponseInterface
    {
        $this->statusCode = 200;

        $this->sendRequest();

        return new ResponseResolver()->executePageable($this);
    }

    abstract public function sendRequest();

    abstract public function getResponseClass(): string;

    abstract public function getResolved(): mixed;

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    protected function makeRequest(): PaginableInterface
    {
        return $this->requestClass::from($this);
    }
}
