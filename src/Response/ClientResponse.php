<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response;


class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{
    private readonly bool $error;

    public function __construct(private readonly mixed             $resolved,
                                private readonly ?string           $message,
                                private readonly ?int              $status,
                                private readonly array             $details,
                                private readonly ?AbstractRequest  $clientRequest = null,
                                private readonly ?ExecutiveRequest $executiveRequest = null,
                                private readonly ?Response         $response = null,
                                private readonly ?Log              $log = null,
    )
    {
        $this->error = is_null($this->resolved);
    }

    public function toArray(): array
    {
        $result = [
            'result' => $this->resolved,
            'error' => $this->error,
            'message' => $this->message,
        ];

        if ($this->error && !empty($this->details)) {
            $result['details'] = $this->details;
        }

        if ($this->clientRequest?->isDebug()) {
            $result['debug'] = [
                'url' => $this->executiveRequest?->getUrlWithQueryParams(),
                'clientRequest' => [
                    'class' => $this->clientRequest::class,
                    'baseUrl' => $this->clientRequest->getBaseUrl(),
                    'url' => $this->clientRequest->getUrl(),
                    'data' => $this->clientRequest->toArray(),
                ],
                'executiveRequest' => $this->executiveRequest?->toArray(),
                'response' => $this->response,
                'status' => $this->status,
                'log' => $this->log->all(),
            ];
        }

        return $result;
    }

    public function toResponse($request): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
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
}
