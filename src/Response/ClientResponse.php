<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;


class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{
    private readonly bool $error;
    protected mixed $result = null;

    private static ?ClientResponse $lastResponse = null;

    public function __construct(protected readonly mixed             $resolved,
                                protected readonly ?string           $message,
                                protected readonly ?int              $status,
                                protected readonly array             $details,
                                protected readonly ?AbstractRequest  $clientRequest = null,
                                protected readonly ?ExecutiveRequest $executiveRequest = null,
                                protected readonly ?Response         $response = null,
                                protected readonly ?Log              $log = null,
    )
    {
        $this->error = is_null($this->resolved);
        $this->result = $resolved;
        self::$lastResponse = $this;
    }

    public static function getLastResponse(): ?ClientResponse
    {
        return self::$lastResponse;
    }

    private function ifFileResolved(): bool
    {
        return $this->resolved instanceof FileResponse;
    }

    public function getDebugInfo(): ?array
    {
        if ($this->clientRequest?->isDebug()) {
            return [
                'url' => $this->executiveRequest?->getUrlWithQueryParams(),
                'clientRequest' => $this->clientRequest->debugInfo(),
                'executiveRequest' => $this->executiveRequest?->toArray(),
                'response' => $this->ifFileResolved() ? 'file' : $this->response?->body(),
                'status' => $this->status,
                'log' => $this->log->all(),
            ];
        }
        return null;
    }

    protected function finalResult(): array
    {
        return [
            'result' => $this->result,
            'error' => $this->error,
            'message' => $this->message,
        ];
    }

    public function toArray(): array
    {
        $result = $this->finalResult();

        if ($this->error && !empty($this->details)) {
            $result['details'] = $this->details;
        }

        if ($debugInfo = $this->getDebugInfo()) {
            $result['debug'] = $debugInfo;
        }

        return $result;
    }

    public function toResponse($request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        if ($this->resolved instanceof FileResponse) {

            if ($this->resolved->hasOneFile()) {
                return $this->resolved->toResponse($request);
            }

            $this->result = $this->resolved->toArray();
        }

        return response()->json($this->toArray(), $this->status);
    }

    public function resolved(): mixed
    {
        return $this->resolved;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getRequest(): ?AbstractRequest
    {
        return $this->clientRequest;
    }

    public function hasError(): bool
    {
        return $this->error;
    }
}
