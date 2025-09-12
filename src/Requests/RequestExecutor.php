<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Contracts\GroupedRequest;
use Brahmic\ClientDTO\Exceptions\AttemptNeededException;
use Brahmic\ClientDTO\Exceptions\CreateDtoValidationException;
use Brahmic\ClientDTO\Exceptions\FailedNestedRequestException;
use Brahmic\ClientDTO\Exceptions\UnexpectedDataException;
use Brahmic\ClientDTO\Exceptions\UnresolvedResponseException;
use Brahmic\ClientDTO\Requests\ResponseDtoResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Response\RequestResult;
use Brahmic\ClientDTO\Support\Log;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class RequestExecutor
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
    private ?string $cachedRawData = null;

    private bool $isAttemptNeeded = false;

    private ?AbstractRequest $clientRequest = null;
    //private ?string $responseClass = null;

    public function __construct()
    {
        $this->log = new Log();
    }


    public function executeGrouped(AbstractRequest $clientRequest)
    {
        $result = collect();

        $this->message = 'Successful';
        $this->statusCode = 200;

        $clientRequest->getRequestClasses()->each(function ($requestClass) use ($clientRequest, $result) {

            /** @var AbstractRequest $request */
            $request = new $requestClass();

            $request->set(...$clientRequest->getOwnPublicProperties());

            $requestResult = new self()->execute($request);

            $result->push($requestResult);

        });

        $this->resolved = $result;
    }

    /**
     * @param AbstractRequest $clientRequest
     * @return RequestResult
     * @throws Throwable
     */
    public function execute(AbstractRequest $clientRequest): RequestResult
    {
        $this->clientRequest = $clientRequest;

        //$this->responseClass = $clientRequest->getResponseClass();

        $this->remainingOfAttempts = $this->getAttempts();

        $this->attempts = $this->getAttempts();

        $this->log->add(sprintf("Execute `%s` request", class_basename($clientRequest)));

        try {

            // Попытка чтения из кеша перед HTTP
            if (!($this->clientRequest instanceof GroupedRequest)) {
                $cached = $this->tryGetFromCache($this->clientRequest);
                if ($cached !== null) {
                    // tryGetFromCache возвращает resolved данные, применяем postProcess
                    $this->resolved = $this->applyPostProcess($this->clientRequest, $cached);
                    $this->setResponseStatus(HttpResponse::HTTP_OK, 'Successful (cached)');

                    // Если включено RAW-кеширование и в кеше есть сырые данные
                    // то RequestResult.rawData должен быть заполнен
                    // Признак RAW/DTO есть внутри CacheManager::get() → CachedResponse
                    // Здесь tryGetFromCache вернул только resolved, поэтому rawData восстанавливаем через details
                    // (rawData будет заполнен ниже через $this->cachedRawData, если tryGetFromCache это установит)

                    $this->finish();

                    return new RequestResult(
                        $this->resolved,
                        null,
                        $this->clientRequest,
                        null,
                        $this->log,
                        $this->message,
                        $this->statusCode,
                        $this->details,
                        $this->cachedRawData,
                    );
                }
            }

            if ($this->clientRequest instanceof GroupedRequest) {

                $this->executeGrouped($this->clientRequest);

            } else {

                $this->executiveRequest = new ExecutiveRequest($this->clientRequest);

                $this->sendRequest();
            }

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

        // Попытка сохранить в кеш после успешного выполнения
        if ($this->resolved !== null && !($this->clientRequest instanceof GroupedRequest)) {
            $this->tryStoreInCache($this->clientRequest, $this->resolved, $this->response?->body());
        }

        $this->finish();

        return new RequestResult(
            $this->resolved,
            $this->response,
            $this->clientRequest,
            $this->executiveRequest,
            $this->log,
            $this->message,
            $this->statusCode,
            $this->details,
            $this->cachedRawData ?? $this->response?->body(),
        );
    }

//    private function createClientResponse(PromiseInterface|Response|null $response = null): ClientResponseInterface
//    {
//        /** @var ClientResponse $responseClass */
//        $responseClass = $this->responseClass;
//
//        return new $responseClass(
//            $this->resolved,
//            $this->message,
//            $this->statusCode,
//            $this->details,
//        )->addResult(new RequestResult(
//            $this->resolved,
//            $this->clientRequest,
//            $this->executiveRequest,
//            $response,
//            $this->log,
//        ));
//    }

    /**
     * @return void
     * @throws RequestException
     */
    protected function handleSuccessfulResponse(): void
    {
        try {
            $this->resolved = new ResponseDtoResolver($this->clientRequest, $this->response)->resolve();

            // Централизованное применение postProcess
            $this->resolved = $this->applyPostProcess($this->clientRequest, $this->resolved);

            $this->setResponseStatus(HttpResponse::HTTP_OK, 'Successful');
        } catch (AttemptNeededException $exception) {
            $this->handleAttemptNeededException($exception, $this->response);
        } catch (UnexpectedDataException $exception) {
            $this->handleUnexpectedDataException($exception, $this->response);
        }
    }

    protected function handleUnsuccessfulResponse(): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Unknown response status');
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
            
            // СОХРАНЕНИЕ В КЕШ ПОСЛЕ УСПЕШНОГО ОТВЕТА
            $this->tryStoreInCache($this->clientRequest, $this->resolved, $this->response->body());
        } else {
            $this->handleUnsuccessfulResponse();
        }

        return $this->response;
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
        $this->setResponseStatus($exception->getCode(), 'Unresolved request. Incorrect data received from the remote server');

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
     * @throws RequestException
     */
    protected function handleAttemptNeededException(AttemptNeededException $exception, Response $response): void
    {
        $this->isAttemptNeeded = true;

        $this->setResponseStatus($exception->getCode(), $exception->getMessage());

        if ($this->hasAttempts()) {
            $this->response = $this->sendRequest();
        }
    }
    protected function handleUnexpectedDataException(UnexpectedDataException $exception, Response $response): void
    {
        $this->setResponseStatus(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage());

        if (app()->hasDebugModeEnabled()) {
            $this->details = [
                $exception->getMessage(),
                "{$exception->getFile()} at line {$exception->getLine()}",
            ];
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

    /**
     * Пересобрать DTO из RAW данных (для RAW кеширования)
     */
    private function processRawResponse(AbstractRequest $request, string $rawData): mixed
    {
        // Создаем фиктивный Response из RAW данных с правильными заголовками JSON
        $fakeResponse = new Response(
            new Psr7Response(200, [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($rawData)
            ], $rawData)
        );
        
        // Используем тот же резолвер, что и для обычных запросов
        return (new ResponseDtoResolver($request, $fakeResponse))->resolve();
    }

    // Новые методы для кеширования HTTP запросов

    /**
     * Попытаться получить данные из кеша (graceful degradation)
     */
    private function tryGetFromCache(AbstractRequest $request): mixed
    {
        try {
            $cacheManager = new \Brahmic\ClientDTO\Cache\CacheManager();
            
            if (!$cacheManager->shouldUseCache($request)) {
                return null;
            }
            
            $cached = $cacheManager->get($request);
            if ($cached !== null) {
                $this->log->add($cached->isRaw ? "Cache hit (RAW)" : "Cache hit (DTO)");

                if ($cached->isRaw) {
                    // RAW кеширование: пересобираем DTO из RAW данных
                    $this->cachedRawData = $cached->rawResponse;
                    
                    // Пересобираем DTO из RAW данных каждый раз
                    $rawData = $cached->rawResponse;
                    $resolvedFromRaw = $this->processRawResponse($request, $rawData);
                    
                    return $resolvedFromRaw;
                } else {
                    // DTO кеширование: используем готовый DTO + устанавливаем raw данные для saveAs()
                    $this->cachedRawData = $cached->rawResponse;
                    return $cached->resolved;
                }
            }
            
            return null;
            
        } catch (\Throwable $e) {
            // Кеш недоступен - логируем и продолжаем без кеша
            $this->log->add("Cache read failed: {$e->getMessage()}");
            
            if ($this->clientRequest->isDebug()) {
                $this->details[] = "Cache unavailable: {$e->getMessage()}";
            }
            
            return null; // = делаем HTTP запрос
        }
    }

    /**
     * Попытаться сохранить результат в кеш (graceful degradation)
     */
    private function tryStoreInCache(AbstractRequest $request, mixed $resolved, ?string $rawData): void
    {
        try {
            $cacheManager = new \Brahmic\ClientDTO\Cache\CacheManager();
            
            if (!$cacheManager->shouldStoreCache($request)) {
                return;
            }
            
            // Не кешируем файлы
            if ($resolved instanceof \Brahmic\ClientDTO\Response\FileResponse) {
                $this->log->add("Skipping cache: FileResponse");
                return;
            }
            
            // Не кешируем null результаты
            if ($resolved === null) {
                $this->log->add("Skipping cache: null result"); 
                return;
            }
            
            // Проверяем размер кешируемых данных: для RAW — по сырому ответу, для DTO — по объекту
            $dataForSizeCheck = $request->getClientDTO()->isRawCacheEnabled() ? ($rawData ?? '') : $resolved;
            if ($cacheManager->isObjectTooLarge($dataForSizeCheck, $request)) {
                $this->log->add("Skipping cache: object exceeds requestCacheSize");
                return;
            }
            
            $cacheManager->store($request, $resolved, $rawData);
            $this->log->add("Stored in cache");
            
        } catch (\Throwable $e) {
            // Не смогли закешировать - не критично, продолжаем
            $this->log->add("Cache write failed: {$e->getMessage()}");
            
            if ($this->clientRequest->isDebug()) {
                $this->details[] = "Cache storage failed: {$e->getMessage()}";
            }
            
            // НЕ бросаем исключение дальше!
        }
    }

    /**
     * Централизованное применение postProcess
     */
    private function applyPostProcess(AbstractRequest $request, mixed $resolved): mixed
    {
        if (method_exists($request, 'postProcess') && $resolved !== null) {
            $request->postProcess($resolved);
        }
        return $resolved;
    }
}
