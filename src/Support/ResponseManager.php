<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Exceptions\PaginationRequestException;
use Brahmic\ClientDTO\Exceptions\PreflightRequestException;
use Brahmic\ClientDTO\Response\ClientResponse;
use Closure;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ResponseManager
{
    private mixed $resolved = null;

    private ?string $message = null;

    private Log $log;

    private ?int $statusCode = null;

    private array $details = [];

    private ?string $responseClass = null;

    public function __construct()
    {
        $this->log = new Log();
    }

    public function execute(Closure $unitOfWork, AbstractRequest $clientRequest): ClientResponse|ClientResponseInterface
    {
        try {

            $this->resolved = $unitOfWork();

            $this->setResponseStatus(HttpResponse::HTTP_OK, 'Successful');

        } catch (PreflightRequestException $exception) {
            $this->handlePreflightRequestException($exception);

        } catch (PaginationRequestException $exception) {
            $this->handlePaginationRequestException($exception);

        } catch (\Throwable $exception) {
            $this->handleThrowableException($exception);
        }

        $responseClass = $clientRequest->getResponseClass();

        return new $responseClass(
            resolved: $this->resolved,
            message: $this->message,
            status: $this->statusCode,
            details: $this->details,
            clientRequest: $clientRequest,
            log: $this->log,
        );
    }

    private function handlePreflightRequestException(PreflightRequestException $exception): void
    {
        $this->setResponseStatus(
            HttpResponse::HTTP_BAD_GATEWAY,
            app()->hasDebugModeEnabled() ? $exception->getMessage() : 'Internal server error, please contact the service administrator'
        );

        if (app()->hasDebugModeEnabled()) {
            $this->details = $exception->getClientResponse()->toArray();
        }

    }

    private function handlePaginationRequestException(PaginationRequestException $exception): void
    {
        $this->setResponseStatus($exception->getCode(), $exception->getMessage());

        if (app()->hasDebugModeEnabled()) {
            $this->details = $exception->getFailed()->toArray();
        }
    }

    private function handleThrowableException(Throwable $exception): void
    {
        $this->setResponseStatus(
            HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
            app()->hasDebugModeEnabled() ? $exception->getMessage() : 'Internal server error, please contact the service administrator'
        );

//        if (app()->hasDebugModeEnabled()) {
//            throw $exception;
//        }
    }

    protected function setResponseStatus(int $statusCode, string $message): void
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
    }
}
