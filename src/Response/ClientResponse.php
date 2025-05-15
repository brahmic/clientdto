<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Contracts\GroupedRequest;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;


class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{

    protected ClientResult $result;
    protected RequestResult $requestResult;

    protected string $message = 'Successful';

    protected int $status = 200;

    protected array $details = [];
    protected bool $grouped = false;

    protected static ?ClientResponse $lastResponse = null;

    public function __construct(RequestResult $requestResult)
    {
        $this->requestResult = $requestResult;

        $this->result = $this->modificatorResult($requestResult);

        $this->message = $requestResult->message;

        $this->status = $requestResult->statusCode;

        $this->details = $requestResult->details;

        $this->grouped = $requestResult->clientRequest instanceof GroupedRequest;

        self::$lastResponse = $this;
    }

    public function toArray(): array
    {
        $final = new ClientResult()
            ->set('message', $this->message)
            ->set('error', $this->hasError())
            ->set('result', $this->result->toArray())
            ->set('details', $this->details, $this->hasError() && !empty($this->details));

        return $final->toArray();
    }


    public function toResponse($request): JsonResponse|Response
    {
        $resolved = $this->resolved();

        if ($resolved instanceof FileResponse) {

            if ($resolved->hasOneFile()) {
                return $resolved->toResponse($request);
            }
        }

        return response()->json($this->toArray(), $this->status);
    }

    public function resolved(): mixed
    {
        return $this->requestResult->resolved;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function hasError(): bool
    {
        return $this->requestResult->hasError();
    }

    public function isGrouped(): bool
    {
        return $this->grouped;
    }


    public static function getLastResponse(): ?ClientResponse
    {
        return self::$lastResponse;
    }

    private function modificatorResult(RequestResult $requestResult): ClientResult
    {
        if ($requestResult?->clientRequest instanceof GroupedRequest) {

            /** @var Collection $collection */
            if ($collection = $requestResult->resolved) {
                $collection->transform(function (RequestResult $requestResult) {
                    return $requestResult
                        ->modifyResult()
                        ->set('key', $requestResult->clientRequest->getKey());
                });
            }
        }

        return $this->modifyResult($requestResult->modifyResult());
    }

    private function ifFileResolved(): bool
    {
        return $this->requestResult->hasFile();
    }

    protected function modifyResult(ClientResult $clientResult): ClientResult
    {
        return $clientResult;
    }
}
