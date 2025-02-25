<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\MimeTypes;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use TypeError;

class Executor
{
    private int $attempts;

    private int $remainingOfAttempts;

    private ?string $message = null;

    private mixed $resolved = null;

    private Log $log;

    private ?int $status = null;

    private array $details = [];

    private ExecutiveRequest $executiveRequest;
    private bool $isAttemptNeeded;

    public function __construct(private readonly AbstractRequest $clientRequest)
    {
        $this->log = new Log();
        $this->remainingOfAttempts = $this->getAttempts();
        $this->attempts = $this->getAttempts();

        $this->log->add('Execute request: ' . class_basename($clientRequest));
    }

    public function execute(): ClientResponseInterface|ClientResponse
    {
        try {
            $this->executiveRequest = new ExecutiveRequest($this->clientRequest);

            $this->log->add('Executive created');

            do {
                $this->pauseIfNeed();

                $this->decreaseAttempt();

                $response = $this->executiveRequest->send();


                $this->obtain($response);



            } while ($this->hasAttempts() && $this->isAttemptNeeded());


        } catch (ValidationException $exception) {

            dump($this->log->all());
            dump($exception->getMessage());
            dd($exception->validator->errors()->all());

        } catch (CannotCreateData $cannotCreateData) {

            $this->message = "Не удалось создать объект {class} для " . $cannotCreateData->getMessage();
            $this->log()->add('Failed to create final object');

        } catch (\Throwable $exception) {

            // todo ошибка в коде непредвиденная
            throw $exception;
            dump('Throwable $exception');
            dd($exception->getMessage());
        }
        $this->log()->add('Completed');

        dump($this->log->all());

        return $this->createClientResponse($response);
    }


    /**
     * @throws \Throwable
     */
    private function obtain(Response $response): void
    {

        if ($response->successful()) {

            $this->resolve($response);

        } elseif ($response->clientError()) { //4xx

            $this->message = 'Ошибка клиента';

        } elseif ($response->serverError()) {  //5xx

            $this->message = 'Ошибка сервера';

        } else {
            $this->message = 'Неожиданный статус';
        }

    }


    private function resolve(Response $response): void
    {
        $this->log()->add(sprintf("Request %s is successful, code: %s",
            class_basename($this->getClientRequest()),
            class_basename($response->status()),
        ));

        if ($this->hasFile($response)) {

            $this->log()->add('File received');

            //$this->resolved = //= вернуть файл типа FILE

        } elseif ($json = $this->tryToGetJson($response)) {

            $this->log()->add('JSON received');;

            $this->resolved = $this->resolveDto($json);

        } else {
            $this->resolved = $response->body();
        }

    }

    private function resolveDto(mixed $data): mixed
    {
        $transformed = $this->prepare($data);

        $class = $this->getClientRequest()::getDtoClass();

        if ($transformed && $class && !$this->isAttemptNeeded) {

            $this->log()->add('DTO resolved!');

            return $class::from($transformed);
        }

        return $data;
    }

    private function prepare(mixed $data): mixed
    {
        $transformed = $data;

        $this->log()->add('Preparing...');

        $this->isAttemptNeeded = false;

        foreach ($this->executiveRequest->getChain() as $object) {

            $transformed = $this->chainTransforming($object, $transformed);

            if ($this->hasAttempts() && $this->isAttemptNeeded = $this->chainIsAttemptNeeded($object, $transformed)) {
                return $transformed;
            }
        }

        return $transformed;
    }

    private function chainTransforming(object $object, mixed $data): mixed
    {
        if (method_exists($object, 'transforming')) {

            return $object->transforming($data);
        }

        return $data;
    }

    private function transforming(mixed $data): mixed
    {
        $result = null;

        foreach ($this->executiveRequest->getChain() as $object) {

            if (method_exists($object, 'transforming')) {

                try {

                    $result = $object->transforming($data);

                    $this->log()->add(sprintf("%s data transforming " . (is_object($data) ? class_basename($data) : gettype($data)) . " >> " . (is_object($result) ? class_basename($result) : gettype($result)), class_basename($object)));
//
//                    if ($result === false || $result === null) {
//
//                        $this->log()->add(sprintf("%s  transforming return null, process interrupted.", class_basename($object)));
//                        return null;
//                    }

                    $data = $result;

                } catch (TypeError $exception) {

                    $this->message = "Ошибка при трансформации для " . (is_object($data) ? class_basename($data) : gettype($data));
                    $this->details[] = $exception->getMessage();
                    $this->status = 500;

                    $this->log()->add("Transforming Process interrupted. {$exception->getMessage()}");
                    return null;

                } catch (CannotCreateData $exception) {

                    $this->message = sprintf("Невозможно создать `%s`", class_basename($object));
                    $this->details[] = $exception->getMessage();
                    $this->status = 500;

                    $this->log()->add(sprintf("Can't create %s", class_basename($object)));
                    return null;

                }
            }
        }

        return $result ?? $data;
    }

    private function isAttemptNeeded(): bool
    {
        return $this->isAttemptNeeded;
    }

    private function chainIsAttemptNeeded(object $object, mixed $transformed): bool
    {
        if (method_exists($object, 'isAttemptNeeded')) {

            if ($object->isAttemptNeeded($transformed, $this) === true) {

                $this->log->add(sprintf("%s wants another attempt to do request %s",
                    class_basename($object),
                    class_basename($this->clientRequest),
                ));

                return true;
            }
        }

        return false;
    }

    private function decreaseAttempt(): void
    {
        dump('Attempt ' . $this->attempt());
        $this->log->add("Attempt {$this->attempt()}");

        $this->remainingOfAttempts -= 1;
    }

    private function pauseIfNeed(): void
    {
//        if ($reset) {
//            $this->remainingOfAttempts = $this->attempts;
//        }

        if ($this->remainingOfAttempts() !== $this->getAttempts()) {
            $this->log->add("Wait ({$this->getAttemptDelay()}ms)...");
            usleep($this->getAttemptDelay() * 1000);
        }

        throw_if($this->remainingOfAttempts < 0, new RuntimeException("Unforeseen call. Attempts out of range."));

    }

    private function createClientResponse(PromiseInterface|Response $response): ClientResponseInterface
    {
        $responseClass = $this->clientRequest->getClientDTO()->getResponseClass();

        return new $responseClass($this->executiveRequest, $response);
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
