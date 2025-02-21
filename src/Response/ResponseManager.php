<?php

namespace Brahmic\ClientDTO\Response;

use Bezopasno\IrbisClient\DTO\ResponseDTO;
use Brahmic\ClientDTO\Builders\ExecutiveRequest;
use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientDTOInterface;
use Brahmic\ClientDTO\Contracts\ClientRequestInterface;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Support\MimeTypes;
use Closure;
use Illuminate\Http\Client\Response;
use Spatie\LaravelData\Data;

class ResponseManager implements ClientResponseInterface
{
    private array $logs = [];
    private ?Data $primaryDTO;

    public function __construct(private readonly ExecutiveRequest $collectedRequest, public Response $response)
    {

    }
    public function make(Response $response): ClientResponseInterface
    {
        if ($response->successful()) {
            $this->addLog('Запрос успешен, код: ' . $response->status());
            //2xx

            if ($this->hasFile($response)) {
                $this->addLog('Получен файл');

                // вернуть файл типа FILE
                //
            }

            if ($json = $this->tryToGetJson($response)) {
                $this->addLog('Получен JSON');

                if ($this->primaryDTO = $this->getAdvanceCreationDTO($json, $this->getClientRequest())) {
                    dump($this->primaryDTO);
                }

            }

            dump($this->logs);
            //if (advanceCreationDTO)
            //todo конкретную реализацию брать у клиента getClientResponseClass
            return new ClientResponse($response);

        } elseif ($response->clientError()) {
            //4xx
            throw new Exception('Ошибка клиента');


        } elseif ($response->serverError()) {
            //5xx

            throw new Exception('Ошибка сервера');

        } else {

            throw new Exception('Неожиданный статус');
        }

        //Если ошибка техническая, возвращаем типовой ответ
        //Если ошибка от валидатора клиента — возвращаем её

        return new ClientResponse(); //todo конкретную реализацию брать у клиента getClientResponseClass
    }


    private function getClientRequest(): ClientRequestInterface
    {
        return $this->collectedRequest->getClientRequest();
    }

    private function getClientDTO(): ClientDTOInterface
    {
        return $this->getClientRequest()->getClientDTO();
    }

    //exp
    public function isAttemptNeeded(): bool
    {
        $args = ResponseDTO::from([]);
        return $this->collectedRequest->isAttemptNeeded($args, $this);
    }

    private function getAdvanceCreationDTO(array $data, ClientRequestInterface $clientRequest): ?Data
    {
        if ($dto = $this->getClientDTO()->advanceCreationDTO($data, $clientRequest)) {
            $this->addLog(sprintf("Создан общий первичный объект %s через метод advanceCreationDTO", class_basename($dto)));
            return $dto;
        }
        return null;
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

    private function addLog(string $message): void
    {
        $this->logs[] = $message;
    }


}
