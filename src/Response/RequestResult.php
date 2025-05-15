<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\GroupedRequest;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Closure;
use Illuminate\Http\Client\Response;

class RequestResult
{
    protected bool $grouped = false;
    protected ClientResult $result;

    public function __construct(
        readonly mixed                    $resolved = null,     //resolved
        public readonly ?Response         $response = null,
        public readonly ?AbstractRequest  $clientRequest = null,
        public readonly ?ExecutiveRequest $executiveRequest = null,
        public readonly ?Log              $log = null,
        public readonly ?string           $message = null,
        public readonly ?int              $statusCode = null,
        public readonly ?array            $details = null,
    )
    {
        $this->result = new ClientResult($resolved);
        $this->grouped = $clientRequest instanceof GroupedRequest;
    }

    public function modifyResult(): ?ClientResult
    {
        return $this->clientRequest?->modifyResult($this);
    }

    public function isGrouped(): bool
    {
        return $this->clientRequest instanceof GroupedRequest;
    }


    public function value(): mixed
    {
        return $this->result->value();
    }


    public function hasError(): bool
    {
        return is_null($this->result->value());
    }

    public function getResult(): ClientResult
    {
        return $this->result;
    }

    public function hasFile(): bool
    {
        return $this->resolved instanceof FileResponse;
    }

    public function debugInfo(): array
    {
        return [
            'url' => $this->executiveRequest?->getUrlWithQueryParams(),
            'clientRequest' => $this->clientRequest?->debugInfo(),
            'executiveRequest' => $this->executiveRequest?->toArray(),
            //'response' => $this->ifFileResolved() ? 'file' : $this->response?->body(), //todo сериализация (кеш) глючит
            'log' => $this->log?->all(),
        ];
    }
}
