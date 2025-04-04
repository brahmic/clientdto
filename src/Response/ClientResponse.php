<?php

namespace Brahmic\ClientDTO\Response;

use Arr;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;


class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{
    private readonly bool $error;

    protected mixed $result = null;

    private static ?ClientResponse $lastResponse = null;

    private array $resultModifiers = [];

    public function __construct(private readonly mixed             $resolved,
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

        $this->result = new ClientResult();

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

    public function getDebugInfo(): array
    {
        return [
            'url' => $this->executiveRequest?->getUrlWithQueryParams(),
            'clientRequest' => $this->clientRequest->debugInfo(),
            'executiveRequest' => $this->executiveRequest?->toArray(),
            'response' => $this->ifFileResolved() ? 'file' : $this->response?->body(),
            'status' => $this->status,
            'log' => $this->log->all(),
        ];
    }

    protected function fillResult(ClientResult $clientResult): void
    {
        $clientResult->set('result', $this->resolved);
    }

    public function modifyResult(Closure $closure): static
    {
        $this->resultModifiers[] = $closure;

        return $this;
    }

    public function toArray(): array
    {
        $this->fillResult($this->result);

        $this->result
            ->set('error', $this->error)
            ->set('message', $this->message)
            ->set('details', $this->details, $this->error && !empty($this->details))
            ->set('debug', $this->getDebugInfo(), $this->clientRequest?->isDebug());

        array_walk($this->resultModifiers, function (Closure $closure) {
            $closure($this->result, $this->resolved);
        });

        return $this->result->toArray();
    }


    public function toResponse($request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        if ($this->resolved instanceof FileResponse) {

            if ($this->resolved->hasOneFile()) {
                return $this->resolved->toResponse($request);
            }

            //$this->result = $this->resolved->toArray();
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
