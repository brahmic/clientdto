<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Requests\ResponseResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Spatie\LaravelData\Data;

abstract class AbstractPageableRequest extends Data
{
    protected ?AbstractRequest $clientRequest = null;

    /** @var string<AbstractRequest>|null  */
    private ?string $requestClass = null;
    private ?int $statusCode = null;

    public function send(): ClientResponse|ClientResponseInterface
    {
        $this->statusCode = 200;

        $this->resolved = $this->sendRequest();

        return new ResponseResolver()->executePageable($this);
    }

    abstract public function sendRequest();

    abstract public function getResponseClass(): string;
    abstract protected function makeRequest(): mixed;

    public function getStatusCode()
    {
        return $this->statusCode;
    }


}
