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
    private mixed $resolved = null;

    private bool $error = false;

    private ?string $message = null;

    private ?int $status = null;    //этот код будет использован для формирования ответа response

    private array $details = [];

    public function __construct(private readonly ExecutiveRequest $executiveRequest, public Response $response)
    {

    }

    public function toArray(): array
    {
        return [
            'result' => $this->resolved,
            'error' => is_null($this->resolved),
            'message' => $this->message,
            'status' => $this->status,
            'details' => $this->details,
            'debug' => [
                'clientRequest' => $this->executiveRequest->getClientRequest()->toArray(),
                'executiveRequest' => $this->executiveRequest,
                'url' => $this->executiveRequest->getUrlWithQueryParams(),
                'response' => [
                    'status' => $this->response->status(),
                    'body' => $this->response->body(),
                ],
                'log' => $this->log()->all(),
            ]
        ];
    }

    private function log(): Log
    {
        return $this->executiveRequest->log();
    }

    public function toResponse($request): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        return response()->json($this->resolved, $this->status);
    }
}
