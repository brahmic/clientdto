<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Attributes\ExtractInputFrom;
use Brahmic\ClientDTO\Attributes\CollectionOf;
use Brahmic\ClientDTO\Attributes\Wrapped;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\WrappedDtoInterface;
use Brahmic\ClientDTO\Exceptions\CreateDtoValidationException;
use Brahmic\ClientDTO\Exceptions\UnexpectedDataException;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\MimeTypes;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ReflectionProperty;
use Brahmic\ClientDTO\Support\Data;

class ResponseDtoResolver
{

    private mixed $resolved = null;

    private Log $log;

    private Response $response;

    private AbstractRequest $clientRequest;

    public function __construct(AbstractRequest $clientRequest, Response $response)
    {
        $this->log = new Log();
        $this->clientRequest = $clientRequest;
        $this->response = $response;
    }

    /**
     * @throws CreateDtoValidationException
     * @throws Exception
     */
    public function resolve(): mixed
    {
        if (!$this->response->successful()) {
            return null;
        }

        $this->log->add(sprintf("Request `%s` is successful, code: %s",
            class_basename($this->clientRequest),
            class_basename($this->response->status()),
        ));

        if ($this->hasFile()) {

            $this->log->add('File received');

            $this->resolved = $this->resolveFile();

        } else {
            $json = $this->tryToGetJson();

            if ($json !== null) {

                $this->log->add('JSON received');

                $this->resolved = $this->resolveDto($json);

            } else {

                if ($this->clientRequest->wantsJson()) {
                    throw new UnexpectedDataException('Invalid response: expected JSON');
                }

                $this->resolved = $this->response->body();
            }

        }

        if (method_exists($this->clientRequest, 'postProcess') && $this->resolved) {
            $this->clientRequest->postProcess($this->resolved);
        }

        return $this->resolved;
    }

    private function resolveFile(): mixed
    {
        $class = $this->getClientRequest()->resolveDtoClass();

        return new $class($this->response);
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

        if ($transformed !== null && $class) {

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

            if (method_exists($this->clientRequest, 'beforeReturn')) {
                $this->clientRequest->beforeReturn($dto);
            }

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


    private function tryToGetJson(): mixed
    {
        if ($this->isJson()) {
            return $this->response->json();
        }

        if (str_contains($this->response->header('Content-Type'), 'text/html') && !is_null($this->response->json())) {
            return $this->response->json();
        }

        return null;
    }

    private function isJson(): bool
    {
        return str_contains($this->response->header('Content-Type'), 'application/json');
    }

    private function hasFile(): bool
    {
        $hasContentType = null;

        if ($contentType = $this->response->header('Content-Type')) {
            $hasContentType = array_find(MimeTypes::MAP, function ($type) use ($contentType) {
                return $type === $contentType;
            });
        };

        $hasContentDisposition = str_contains($this->response->header('Content-Disposition'), 'attachment');

        return $hasContentType || $hasContentDisposition;
    }

    private function getClientRequest(): ClientRequestInterface
    {
        return $this->clientRequest;
    }

    private function getClientRequestClass(): string
    {
        return $this->clientRequest::class;
    }


}
