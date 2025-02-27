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
    private readonly bool $error;

    public function __construct(private readonly mixed              $resolved,
                                private readonly ?string            $message,
                                private readonly ?int               $status,
                                private readonly array             $details,
                                private readonly ?ExecutiveRequest $executiveRequest,
                                private readonly ?Response          $response,
                                private readonly ?Log               $log,
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
                'clientRequest' => $this->executiveRequest?->getClientRequest(),
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
