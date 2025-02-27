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
use RuntimeException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use TypeError;

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

    private bool $isAttemptNeeded = false;

    public function __construct(private readonly AbstractRequest $clientRequest)
    {
        $this->log = new Log();
        $this->remainingOfAttempts = $this->getAttempts();
        $this->attempts = $this->getAttempts();

        $this->log->add('Execute request: ' . class_basename($clientRequest));
    }

    public function execute(): ClientResponseInterface|ClientResponse
    {
        $response = null;

        try {

            $this->executiveRequest = new ExecutiveRequest($this->clientRequest);

            $response = $this->sendRequest();

        } catch (CreateDtoValidationException $exception) {

            $this->statusCode = 400;
            $this->message = "The data received does not correspond to the declaration of {$exception->getClass()}";
            $this->details = $exception->validator->errors()->all();

        } catch (ValidationException $exception) {

            $this->statusCode = 400;
            $this->message = $exception->getMessage();
            $this->details = $exception->validator->errors()->all();

        } catch (CannotCreateData $cannotCreateData) {

            $this->message = "Can't create object {class} " . $cannotCreateData->getMessage();
            $this->statusCode = 500;

            $this->log()->add('Failed to create object' . $cannotCreateData->getMessage());

        } catch (\Throwable $exception) {

            $this->message = $exception->getMessage();
            $this->statusCode = $exception->getCode();

//            throw $exception;
        }

        $this->log()->add('Completed');

        dump($this->log->all());

        return $this->createClientResponse($response);


    }

    /**
     * @throws RequestException
     */
    public function sendRequest(): PromiseInterface|Response
    {
        $this->isAttemptNeeded = false;

        $response = $this->executiveRequest->send();

        $response->throwIfClientError()->throwIfServerError();

        $this->pauseIfNeed();

        $this->decreaseAttempt();

        if ($response->successful()) {

            try {

                $this->resolve($response);

                $this->statusCode = 200;
                $this->message = 'Successful';

            } catch (AttemptNeededException $exception) {

                $this->isAttemptNeeded = true;

                $this->statusCode = 202;
                $this->message = 'Wait for response';

                if ($this->hasAttempts()) {
                    $response = $this->sendRequest();
                }
            }

        } else {
            $this->statusCode = 500;
            $this->message = 'Unknown response status';
        }

        return $response;
    }


    private function resolve(Response $response): void
    {
        $this->log()->add(sprintf("Request %s is successful, code: %s",
            class_basename($this->getClientRequest()),
            class_basename($response->status()),
        ));

        if ($this->hasFile($response)) {

            $this->log()->add('File received');

            //$this->resolved = //$this->resolveFile($xxxxxxx); вернуть файл типа FILE

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
        //файл, который файл

    }

    private function resolveDto(mixed $data): mixed
    {
        $transformed = $this->prepare($data);

        $class = $this->getClientRequest()::getDtoClass();

        if ($transformed && $class) {

            try {

                $dto = $class::validateAndCreate($transformed);

            } catch (ValidationException $exception) {

                throw new CreateDtoValidationException($exception->validator)->setClass($class);

            }

            $this->log()->add("DTO `" . class_basename($dto) . "` resolved!");

            return $dto;
        }

        return $transformed;
    }


    private function prepare(mixed $data): mixed
    {
        $transformed = $data;

        $this->log()->add('Preparing...');

        foreach ($this->executiveRequest->getChain() as $object) {

            $transformed = $this->chainTransforming($object, $transformed);

            $this->validation($object, $transformed);
        }

        return $transformed;
    }

    private function validation(object $object, mixed $data): mixed
    {
        return method_exists($object, 'validation') ? $object->validation($data, $this->clientRequest) : $data;
    }

    private function chainTransforming(object $object, mixed $data): mixed
    {
        return method_exists($object, 'transforming') ? $object->transforming($data, $this->clientRequest) : $data;
    }

    private function decreaseAttempt(): void
    {
        $this->log->add("Attempt {$this->attempt()}");

        $this->remainingOfAttempts -= 1;
    }

    private function pauseIfNeed(): void
    {
        if ($this->remainingOfAttempts() !== $this->getAttempts()) {
            $this->log->add("Wait {$this->getAttemptDelay()}ms...");
            usleep($this->getAttemptDelay() * 1000);
        }

        throw_if($this->remainingOfAttempts < 0, new RuntimeException("Unforeseen call. Attempts out of range."));
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
            $response);
    }

    public function isResolved(): bool
    {
        return is_null($this->resolved);
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
}
