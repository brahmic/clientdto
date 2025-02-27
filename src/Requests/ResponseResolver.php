<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Exceptions\AttemptNeededException;
use Brahmic\ClientDTO\Exceptions\CreateDtoValidationException;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\MimeTypes;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ResponseResolver
{
    private int $attempts;

    private int $remainingOfAttempts;

    private ?string $message = null;

    private mixed $resolved = null;

    private Log $log;

    private ?int $statusCode = null;

    private array $details = [];

    private ?ExecutiveRequest $executiveRequest = null;
    private ?Response $response = null;

    private bool $isAttemptNeeded = false;

    public function __construct(private readonly AbstractRequest $clientRequest)
    {
        $this->log = new Log();
        $this->remainingOfAttempts = $this->getAttempts();
        $this->attempts = $this->getAttempts();

        $this->log->add(sprintf("Execute `%s` request", class_basename($clientRequest)));
    }

    /**
     * @return ClientResponseInterface|ClientResponse
     * @throws Throwable
     */
    public function execute(): ClientResponseInterface|ClientResponse
    {
        $response = null;

        try {
            $this->executiveRequest = new ExecutiveRequest($this->clientRequest);
            $response = $this->sendRequest();
        } catch (CreateDtoValidationException $exception) {
            $this->handleCreateDtoValidationException($exception);
        } catch (ValidationException $exception) {
            $this->handleValidationException($exception);
        } catch (CannotCreateData $exception) {
            $this->handleCannotCreateDataException($exception);
        } catch (Throwable $exception) {
            $this->handleGenericException($exception);
        }

        $this->logExecution();

        return $this->createClientResponse($response);
    }

    private function createClientResponse(PromiseInterface|Response|null $response = null): ClientResponseInterface
    {
        $responseClass = $this->clientRequest->getClientDTO()->getResponseClass();

        return new $responseClass(
            $this->resolved,
            $this->message,
            $this->statusCode,
            $this->details,
            $this->executiveRequest,
            $response,
            $this->log,
            $this->isAttemptNeeded,
        );
    }

    /**
     * @throws RequestException
     */
    public function sendRequest(): PromiseInterface|Response
    {
        $this->isAttemptNeeded = false;

        $this->response = $this->executiveRequest->send();

        $this->response->throwIfClientError()->throwIfServerError();

        $this->nextAttempt();

        if ($this->response->successful()) {
            $this->handleSuccessfulResponse();
        } else {
            $this->handleUnsuccessfulResponse();
        }

        return $this->response;
    }


    private function resolve(Response $response): void
    {
        $this->log()->add(sprintf("Request `%s` is successful, code: %s",
            class_basename($this->getClientRequest()),
            class_basename($response->status()),
        ));

        if ($this->hasFile($response)) {

            $this->log()->add('File received');

            $this->resolved = $this->resolveFile($response);

        } elseif ($json = $this->tryToGetJson($response)) {

            $this->log()->add('JSON received');

            $this->resolved = $this->resolveDto($json);

        } else {
            $this->resolved = $response->body();
        }
    }

    private function resolveFile(mixed $data): mixed
    {
        //файл, который надо скачать, распаковать и прочее
    }

    private function resolveDto(mixed $data): mixed
    {
        $transformed = $this->prepare($data);

        $class = $this->getClientRequest()::getDtoClass();

        if ($transformed && $class) {

            try {

                $dto = $class::validateAndCreate($transformed);

            } catch (ValidationException $exception) {

                throw new CreateDtoValidationException($exception->validator, $this->response)->setClass($class);

            }

            $this->log()->add("DTO `" . class_basename($dto) . "` resolved!");

            return $dto;
        }

        return $transformed;
    }

    private function prepare(mixed $data): mixed
    {
        $current = $data;

        $this->log()->add('Preparing...');

        foreach ($this->executiveRequest->getChain() as $object) {

            $current = $this->handle($object, $current);

            $this->validation($object, $current);
        }

        return $current;
    }

    private function validation(object $object, mixed $data): void
    {
        if (method_exists($object, 'validation')) {
            $object->validation($data, $this->clientRequest);
        }
    }

    private function handle(object $object, mixed $data): mixed
    {
        return method_exists($object, 'handle') ? $object->handle($data, $this->clientRequest) : $data;
    }

    private function nextAttempt(): void
    {
        if ($this->remainingOfAttempts() !== $this->getAttempts()) {
            $this->log->add("Wait {$this->getAttemptDelay()}ms...");
            usleep($this->getAttemptDelay() * 1000);
        }

        $this->log->add("Attempt {$this->attempt()}");

        $this->remainingOfAttempts -= 1;
    }

    private function tryToGetJson(Response $response): mixed
    {
        if ($this->isJson($response)) {
            return $response->json();
        }

        if (str_contains($response->header('Content-Type'), 'text/html') && $json = $response->json()) {
            return $json;
        }

        return null;
    }

    private function isJson(Response $response): bool
    {
        return str_contains($response->header('Content-Type'), 'application/json');
    }

    private function hasFile(Response $response): bool
    {
        $hasContentType = null;

        if ($contentType = $response->header('Content-Type')) {
            $hasContentType = array_find(MimeTypes::MAP, function ($type) use ($contentType) {
                return $type === $contentType;
            });
        };

        $hasContentDisposition = str_contains($response->header('Content-Disposition'), 'attachment');

        return $hasContentType || $hasContentDisposition;
    }

    private function getClientRequest(): ClientRequestInterface
    {
        return $this->executiveRequest->getClientRequest();
    }

    private function getAttempts(): int
    {
        return $this->clientRequest->getAttempts();
    }

    private function getAttemptDelay(): int
    {
        return $this->clientRequest->getAttemptDelay();
    }

    private function hasAttempts(): bool
    {
        return $this->remainingOfAttempts > 0;
    }

    private function attempt(): int
    {
        return ($this->attempts - $this->remainingOfAttempts) + 1;
    }

    private function remainingOfAttempts(): int
    {
        return $this->remainingOfAttempts;
    }

    public function log(): Log
    {
        return $this->log;
    }

    protected function handleCreateDtoValidationException(CreateDtoValidationException $exception): void
    {
        $message = "The data received does not correspond to the declaration of {$exception->getClass()}";
        $this->statusCode = HttpResponse::HTTP_INTERNAL_SERVER_ERROR;

        if (app()->hasDebugModeEnabled()) {
            $this->message = $message;
            $this->details = $exception->validator->errors()->all();
        } else {
            $this->message = 'Data error, please contact the service administrator';
        }
    }

    protected function handleValidationException(ValidationException $exception): void
    {
        $this->statusCode = HttpResponse::HTTP_BAD_REQUEST;
        $this->message = $exception->getMessage();
        $this->details = $exception->validator->errors()->all();
    }

    protected function handleCannotCreateDataException(CannotCreateData $exception): void
    {
        $this->message = "Can't create object {class} " . $exception->getMessage();
        $this->statusCode = HttpResponse::HTTP_INTERNAL_SERVER_ERROR;
    }

    protected function handleGenericException(Throwable $exception): void
    {
        $this->message = $exception->getMessage();
        $this->statusCode = $exception->getCode();

        if (app()->hasDebugModeEnabled()) {
            throw $exception;
        }
    }

    /**
     * @return void
     * @throws RequestException
     */
    protected function handleSuccessfulResponse(): void
    {
        try {
            $this->resolve($this->response);
            $this->setResponseStatus(HttpResponse::HTTP_OK, 'Successful');
        } catch (AttemptNeededException $exception) {
            $this->handleAttemptNeededException($exception);
        }
    }

    /**
     * @throws RequestException
     */
    protected function handleAttemptNeededException(AttemptNeededException $exception): void
    {
        $this->isAttemptNeeded = true;

        $this->setResponseStatus(HttpResponse::HTTP_ACCEPTED, $exception->getMessage());

        if ($this->hasAttempts()) {
            $this->response = $this->sendRequest();
        }
    }

    protected function handleUnsuccessfulResponse(): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Unknown response status');
    }

    protected function setResponseStatus(int $statusCode, string $message): void
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
    }

    protected function logExecution(): void
    {
        $this->log->add($this->message);
        $this->log->add('Finish');
    }
}
