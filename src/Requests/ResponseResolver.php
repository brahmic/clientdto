<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Attributes\ExtractInputFrom;
use Brahmic\ClientDTO\Attributes\CollectionOf;
use Brahmic\ClientDTO\Attributes\Wrapped;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Contracts\WrappedDtoInterface;
use Brahmic\ClientDTO\Exceptions\AttemptNeededException;
use Brahmic\ClientDTO\Exceptions\CreateDtoValidationException;
use Brahmic\ClientDTO\Exceptions\FailedNestedRequestException;
use Brahmic\ClientDTO\Exceptions\UnresolvedResponseException;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\MimeTypes;
use Brahmic\ClientDTO\Support\RequestHelper;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionProperty;
use Brahmic\ClientDTO\Support\Data;
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

    private ?AbstractRequest $clientRequest = null;
    private ?string $responseClass = null;

    public function __construct()
    {
        $this->log = new Log();
    }

    /**
     * @param AbstractRequest $clientRequest
     * @return ClientResponseInterface|ClientResponse
     */
    public function execute(AbstractRequest $clientRequest): ClientResponseInterface|ClientResponse
    {
        $this->clientRequest = $clientRequest;
        $this->responseClass = $clientRequest->getResponseClass();
        $this->remainingOfAttempts = $this->getAttempts();
        $this->attempts = $this->getAttempts();

        $this->log->add(sprintf("Execute `%s` request", class_basename($clientRequest)));

        try {
            $this->executiveRequest = new ExecutiveRequest($this->clientRequest);

            $this->sendRequest();

        } catch (CreateDtoValidationException $exception) {
            $this->handleCreateDtoValidationException($exception);
        } catch (ValidationException $exception) {
            $this->handleValidationException($exception);
        } catch (ConnectionException $exception) {
            $this->handleConnectionException($exception);
        } catch (RequestException $exception) {
            $this->handleRequestException($exception);
        } catch (CannotCreateData $exception) {
            $this->handleCannotCreateDataException($exception);
        } catch (UnresolvedResponseException $exception) {
            $this->handleUnresolvedResponse($exception);
        } catch (FailedNestedRequestException $exception) {
            $this->handleFailedRequestException($exception);
        } catch (Throwable $exception) {
            $this->handleGenericException($exception);
        }

        $this->finish();

        return $this->createClientResponse($this->response);
    }

    private function createClientResponse(PromiseInterface|Response|null $response = null): ClientResponseInterface
    {
        return new $this->responseClass(
            $this->resolved,
            $this->message,
            $this->statusCode,
            $this->details,
            $this->clientRequest,
            $this->executiveRequest,
            $response,
            $this->log,
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


    /**
     * @throws CreateDtoValidationException
     * @throws Exception
     */
    private function resolve(Response $response): void
    {
        $clientRequest = $this->getClientRequest();

        $this->log->add(sprintf("Request `%s` is successful, code: %s",
            class_basename($clientRequest),
            class_basename($response->status()),
        ));


        if ($this->hasFile($response)) {

            $this->log->add('File received');

            $this->resolved = $this->resolveFile($response);

        } else {
            $json = $this->tryToGetJson($response);

            if ($json !== null) {

                $this->log->add('JSON received');

                $this->resolved = $this->resolveDto($json);

            } else {

                if ($clientRequest->wantsJson()) {
                    throw new Exception('Invalid response: expected JSON');
                }

                $this->resolved = $response->body();
            }

        }

        if (method_exists($clientRequest, 'postProcess') && $this->resolved) {
            $clientRequest->postProcess($this->resolved);
        }
    }

    private function resolveFile(Response $response): mixed
    {
        $class = $this->getClientRequest()->resolveDtoClass();

        return new $class($response);
    }

    private function resolveDto(mixed $data): mixed
    {
        $transformed = $this->prepare($data);

        $class = $this->getClientRequest()->resolveDtoClass();

        //*************************************************************************************************************//
        // Extract data using dot notation
        $collectionInputName = $this->getExtractInputFrom($this->getClientRequest()::class);

        if ($collectionInputName) {

            $transformed = Arr::get($transformed, $collectionInputName->filedName);

            if (!is_array($transformed)) {
                throw new Exception("Unable to extract data by key `$collectionInputName->filedName` in ExtractInputFrom attribute for request {$this->getClientRequestClass()}.");
            };
        }
        //*************************************************************************************************************//

        if ($transformed && $class) {

            try {

                if (is_subclass_of($class, Data::class)) {

                    $transformed = $this->handle($class, $transformed);

                    /** @var  $wrapped */
                    if ($wrapped = $this->getDtoWrapper($this->getClientRequest()::class)) {

                        if (is_subclass_of($class, WrappedDtoInterface::class)) {
                            $class::setWrapped($wrapped->class);
                            $dto = $this->validateAndCreate($class, $transformed);
                        } else {
                            throw new Exception('If the `Wrapped` attribute is specified, then `$dto` must implement the `DtoWrapperInterface` interface.');
                        }

                    } else {
                        $dto = $this->validateAndCreate($class, $transformed);
                    }

                } else {

                    $dtoCollectionOf = $this->getDtoCollectionOf($this->getClientRequest()::class);

                    if ($dtoCollectionOf && $dtoCollectionOf->filedName) {
                        $transformed = $transformed[$dtoCollectionOf->filedName];
                    }

                    /*
                     * If another class is specified, then we create the final object through the constructor,
                     * or if there is a CollectionOf attribute, then we create the collection in a special
                     * way through the cast
                     */
                    $dto = new $class($transformed);

                    if ($dtoCollectionOf) {
                        $dto = $dto->map(function ($value) use ($dtoCollectionOf) {
                            $value = $this->handle($dtoCollectionOf->class, $value);
                            return $this->validateAndCreate($dtoCollectionOf->class, $value);
                        });
                    }
                }

            } catch (ValidationException $exception) {
                throw new CreateDtoValidationException($exception->validator, $this->response)->setClass($class);
            }

            $this->log->add("DTO `" . class_basename($dto) . "` resolved!");

            return $dto;
        }

        return $transformed;
    }

    private function validateAndCreate(string $class, mixed $data): mixed
    {
        /** @var Data $class */

        return $class::validateAndCreate($data);
    }

    private function getDtoCollectionOf(string $className): ?CollectionOf
    {
        return $this->getAttributes($className, 'dto', CollectionOf::class);
    }

    private function getExtractInputFrom(string $className): ?ExtractInputFrom
    {
        return $this->getAttributes($className, 'dto', ExtractInputFrom::class);
    }


    private function getDtoWrapper(string $className): ?Wrapped
    {
        return $this->getAttributes($className, 'dto', Wrapped::class);
    }

    private function getAttributes(string $propertyClass, string $propertyName, string $attributeClass): mixed
    {
        // Проверяем, существует ли свойство в классе
        if (!property_exists($propertyClass, $propertyName)) {
            throw new InvalidArgumentException("Property {$propertyName} does not exist in class {$propertyClass}.");
        }

        // Создаем ReflectionProperty для свойства
        $reflectionProperty = new ReflectionProperty($propertyClass, $propertyName);

        // Получаем атрибуты свойства
        $attributes = $reflectionProperty->getAttributes($attributeClass);

        // Если атрибут найден, возвращаем его экземпляр
        if (!empty($attributes)) {
            return $attributes[0]->newInstance();
        }

        $this->log->add("CollectionOf attribute not specified for `dto` property in class {$propertyClass}.");

        return null;
    }

    private function prepare(mixed $data): mixed
    {
        $current = $data;

        $this->log->add('Preparing...');

        foreach ($this->clientRequest->getChain() as $chain) {

            $current = $this->handle($chain, $current);

            $this->validation($chain, $current);
        }

        return $current;
    }

    private function validation(object $object, mixed $data): void
    {
        if (method_exists($object, 'validation')) {
            $object->validation($data, $this->clientRequest, $this->response); //todo Context object, also for handle method
        }
    }

    private function handle(object|string $target, mixed $data): mixed
    {
        $class = is_object($target) ? get_class($target) : $target;

        return method_exists($class, 'handle') ? $class::handle($data, $this->clientRequest) : $data;
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

        if (str_contains($response->header('Content-Type'), 'text/html') && !is_null($response->json())) {
            return $response->json();
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

    private function getClientRequestClass(): string
    {
        return $this->executiveRequest->getClientRequest()::class;
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

    protected function handleCreateDtoValidationException(CreateDtoValidationException $exception): void
    {
        if (app()->hasDebugModeEnabled()) {
            $message = "The data received does not correspond to the declaration of {$exception->getClass()}. Please check field types and the specified DTO class, also `handle` method of the client, resources or request";


            $json = $this->tryToGetJson($exception->getResponse());

            $this->details = [
                'errors' => $exception->validator->errors()->all(),
                'receivedData' => $json,
            ];
        } else {
            $message = "Data error, please contact the service administrator";
        }

        $this->setResponseStatus(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $message);
    }

    protected function handleValidationException(ValidationException $exception): void
    {
        $message = "Input data validation error";

        if (app()->hasDebugModeEnabled()) {
            $class = $this->clientRequest::class;
            $message = "Input data validation error in the {$class}";
        }

        $this->setResponseStatus(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $message);

        $this->details = $exception->validator->errors()->all();
    }

    protected function handleConnectionException(ConnectionException $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_BAD_GATEWAY, 'The data server is not responding');

        if (app()->hasDebugModeEnabled()) {
            $this->details = [$exception->getMessage()];
        }
    }

    protected function handleRequestException(RequestException $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_BAD_REQUEST, 'Bad request, please contact the service administrator');

        $json = $this->tryToGetJson($exception->response);

        $this->response = $exception->response;

        if (app()->hasDebugModeEnabled()) {
            $this->details = is_array($json) ? $json : [$exception->response->body()];
        }
    }

    protected function handleCannotCreateDataException(CannotCreateData $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, "Can't create object {class} " . $exception->getMessage());
    }

    protected function handleUnresolvedResponse(UnresolvedResponseException $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Unresolved request. Incorrect data received from the remote server');

        if (app()->hasDebugModeEnabled()) {

            $json = $this->tryToGetJson($exception->getResponse());

            $this->details = [
                'message' => $exception->getMessage(),
                'received' => $json ? $json : $exception->getResponse()->body(),
            ];
        }
    }

    protected function handleFailedRequestException(FailedNestedRequestException $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Request failed, please try again later or contact the service administrator');

        if (app()->hasDebugModeEnabled()) {
            $lastResponse = ClientResponse::getLastResponse();

            $this->details = [
                'message' => $exception->getMessage(),
                'response' => $lastResponse->toArray(),
            ];
        }
    }

    protected function handleGenericException(Throwable $exception): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error, please contact the service administrator');

        if (app()->hasDebugModeEnabled()) {
            $this->details = [
                $exception->getMessage(),
                "{$exception->getFile()} at line {$exception->getLine()}",
            ];
        }

        // todo !!!!в конфликте!!!
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

    protected function handleUnsuccessfulResponse(): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Unknown response status');
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

    protected function setResponseStatus(int $statusCode, string $message): void
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
    }

    protected function finish(): void
    {
        $this->log->add($this->message);
        $this->log->add('Finish');
    }
}
