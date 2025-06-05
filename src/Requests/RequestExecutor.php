<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Brahmic\ClientDTO\Contracts\GroupedRequest;
use Brahmic\ClientDTO\Exceptions\AttemptNeededException;
use Brahmic\ClientDTO\Exceptions\CreateDtoValidationException;
use Brahmic\ClientDTO\Exceptions\FailedNestedRequestException;
use Brahmic\ClientDTO\Exceptions\UnresolvedResponseException;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Response\RequestResult;
use Brahmic\ClientDTO\Support\Log;
use GuzzleHttp\Promise\PromiseInterface;
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

            $this->setResponseStatus(HttpResponse::HTTP_OK, 'Successful');
        } catch (AttemptNeededException $exception) {
            $this->handleAttemptNeededException($exception, $this->response);
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
