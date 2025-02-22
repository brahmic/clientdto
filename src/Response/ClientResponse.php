<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Requests\ExecutiveRequest;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\MimeTypes;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use TypeError;

class ClientResponse implements ClientResponseInterface
{
    private mixed $resolved;

    public function __construct(private readonly ExecutiveRequest $executiveRequest, public Response $response)
    {
        $this->make($this->response);
    }

    public function getExecutiveRequest(): ExecutiveRequest
    {
        return $this->executiveRequest;
    }
    private function make(Response $response)
    {
        if ($response->successful()) {

            Log::add(sprintf("Request %s is successful, code: %s",
                class_basename($this->getClientRequest()),
                class_basename($response->status()),
            ));
            //2xx

            if ($this->hasFile($response)) {
                Log::add('File received');
                //todo $this->result = вернуть файл типа FILE
            } elseif ($json = $this->tryToGetJson($response)) {

                Log::add('JSON received');

                $transformed = $this->transforming($json);

                $class = $this->getClientRequest()::getDtoClass();
                dd(Log::all());
                $this->resolved = $class::from($transformed);


            } else {
                $this->resolved = $response->body();
            }

            //todo конкретную реализацию брать у клиента getClientResponseClass


        } elseif ($response->clientError()) {

            dump($this->executiveRequest);
            dd($response->json());
            //4xx
            throw new Exception('Ошибка клиента');

        } elseif ($response->serverError()) {
            //5xx

            throw new Exception('Ошибка сервера');

        } else {

            throw new Exception('Неожиданный статус');
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

                    Log::add(sprintf("%s data transforming " . (is_object($data) ? class_basename($data) : gettype($data)) . " >> " . (is_object($result) ? class_basename($result) : gettype($result)), class_basename($object)));

                    if ($result === false || $result === null) {

                        Log::add(sprintf("%s  transforming return null, process interrupted.", class_basename($object)));
                        return null;
                    }

                    return $result;

                } catch (TypeError $exception) {

                    Log::add("Process interrupted. {$exception->getMessage()}");
                    return null;

                } catch (CannotCreateData $exception) {

                    Log::add(sprintf("Can't create %s", class_basename($object)));
                    return null;

                }
            }
        }
        return $result ?: $data;
    }


    //exp
    public function isAttemptNeeded(): bool
    {
        if (!$this->resolved) {
            return false;
        }

        foreach ($this->executiveRequest->getChain() as $chain) {
            if (method_exists($chain, 'isAttemptNeeded')) {
                if ($chain->isAttemptNeeded($this->resolved, $this) === true) {

                    //Log::add(sprintf("Запросом %s востребована ещё одна попытка из %s::isAttemptNeeded",
                    Log::add(sprintf("%s::isAttemptNeeded requested another attempt to request %s",
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
}
