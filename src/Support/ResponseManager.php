<?php

namespace Brahmic\ClientDTO\Support;

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


    public function execute(Closure $unitOfWork, string $responseClass): ClientResponse|ClientResponseInterface
    {
        $this->statusCode = 200;

        try {

            $this->resolved = $unitOfWork();


        } catch (PreflightRequestException $exception) {
            $this->handlePreflightRequestException($exception);

        } catch (PaginationRequestException $exception) {
            $this->handlePaginationRequestException($exception);

        } catch (\Throwable $exception) {
            $this->handleThrowableException($exception);
        }


        return new $responseClass(
            resolved: $this->resolved,
            message: $this->message,
            status: $this->statusCode,
            details: $this->details,
            log: $this->log,
        );
    }

    private function handlePreflightRequestException(PreflightRequestException $exception): void
    {
        $this->setResponseStatus(
            HttpResponse::HTTP_BAD_GATEWAY,
            app()->isLocal() ? $exception->getMessage() : 'Internal server error, please contact the service administrator'
        );

        if (app()->hasDebugModeEnabled() && app()->isLocal()) {
            $this->details = $exception->getClientResponse()?->toArray();
        }
    }

    private function handlePaginationRequestException(PaginationRequestException $exception): void
    {
        $this->setResponseStatus($exception->getCode(), $exception->getMessage());

        if (app()->hasDebugModeEnabled() && app()->isLocal()) {
            $this->details = $exception->getFailed()->toArray();
        }
    }

    private function handleThrowableException(Throwable $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage());
    }

    protected function setResponseStatus(int $statusCode, string $message): void
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
    }
}
