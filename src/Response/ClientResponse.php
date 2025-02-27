<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response;


class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{
    public readonly bool $error;

    public function __construct(public readonly mixed              $resolved,
                                public readonly ?string            $message,
                                public readonly ?int               $status,
                                private readonly array             $details,
                                private readonly ?ExecutiveRequest $executiveRequest,
                                public readonly ?Response          $response,
                                public readonly ?Log               $log,
    )
    {

        $this->error = is_null($this->resolved);
    }

    public function toArray(): array
    {
        return [
            'result' => $this->error,
            'error' => is_null($this->resolved),
            'message' => $this->message,
            'status' => $this->status,
            'details' => $this->details,
            'debug' => [
                'url' => $this->executiveRequest?->getUrlWithQueryParams(),
                'clientRequest' => $this->executiveRequest?->getClientRequest()->toArray(),
                'executiveRequest' => $this->executiveRequest,
                'response' => $this->response,
                'log' => $this->log->all(),
            ]
        ];
    }

    public function toResponse($request): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        return response()->json($this->resolved, $this->status);
    }

    public function resolved(): mixed
    {
        return $this->resolved;
    }
}
