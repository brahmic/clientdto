<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\MimeTypes;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use TypeError;

class ClientResponse implements ClientResponseInterface, Arrayable, Responsable
{
    private mixed $resolved = null;
    private mixed $result = null;
    //private bool $error = false;
    private ?string $message = null;
    private ?int $status = null;    //этот код будет использован для формирования ответа response
    private array $details = [];

    public function __construct(private readonly ExecutiveRequest $executiveRequest, public Response $response)
    {
        $this->status = $this->response->status();
        $this->make($this->response);
    }

    public function getExecutiveRequest(): ExecutiveRequest
    {
        return $this->executiveRequest;
    }

    private function make(Response $response)
    {
        if ($response->successful()) {

            $this->log()->add(sprintf("Request %s is successful, code: %s",
                class_basename($this->getClientRequest()),
                class_basename($response->status()),
            ));
            //2xx

            if ($this->hasFile($response)) {

                $this->log()->add('File received');

                //$this->resolved = //= вернуть файл типа FILE

            } elseif ($json = $this->tryToGetJson($response)) {

                $this->log()->add('JSON received');

                if ($transformed = $this->transforming($json) && $class = $this->getClientRequest()::getDtoClass()) {
                    try {
                        $this->resolved = $class::from($transformed);
                    } catch (CannotCreateData $cannotCreateData) {
                        $this->message = "Не удалось создать объект {$class} для ";
                        $this->log()->add('Failed to create final object');
                    }
                }

            } else {
                $this->resolved = $response->body();
            }
            //dd($this->log()->all());
            $this->log()->add('Completed');


        } elseif ($response->clientError()) { //4xx

            $this->message = 'Ошибка клиента';

        } elseif ($response->serverError()) {  //5xx

            $this->message = 'Ошибка сервера';

        } else {
            $this->message = 'Неожиданный статус';
        }

        //Если ошибка техническая, возвращаем типовой ответ
        //todo Если ошибка от валидатора клиента — возвращаем её. правильность заполнения?
        //throw new Exception('Неизвестная ошибка');
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

        return $result ?: $data;
    }

    public function isResolved(): bool
    {
        return is_null($this->resolved);
    }

    public function isAttemptNeeded(): bool
    {
        if (!$this->isResolved()) {
            return false;
        }

        foreach ($this->executiveRequest->getChain() as $chain) {
            if (method_exists($chain, 'isAttemptNeeded')) {
                if ($chain->isAttemptNeeded($this->resolved, $this) === true) {

                    //$this->log()->add(sprintf("Запросом %s востребована ещё одна попытка из %s::isAttemptNeeded",
                    $this->log()->add(sprintf("%s::isAttemptNeeded requested another attempt to request %s",
                        class_basename($chain),
                        class_basename($this->getClientRequest()),
                    ));

                    return true;
                }
            }
        }

        return false;
    }

    private function getClientRequest(): ClientRequestInterface
    {
        return $this->executiveRequest->getClientRequest();
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

    public function toArray(): array
    {
        /*
         * Ошибки, присланные от поставщика данных
         *  - клиента
         * Ошибки серверные
         *
         * Ошибки внутренние
         *  - трансформация и пр.
         *
         *
         *
         */
        return [
            'result' => $this->resolved,
            'error' => is_null($this->resolved),
            'message' => $this->message,
            'status' => $this->status,
            'details' => $this->details,
            'debug' => [
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

    public function toResponse($request)
    {
        return response()->json($this->resolved, $this->status);
    }
}
