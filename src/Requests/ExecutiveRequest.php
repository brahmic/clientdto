<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\RequestHelper;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Validator;

class ExecutiveRequest implements Arrayable
{
//    private int $attempts;
//
//    private int $remainingOfAttempts;

    private string $url;

    private int $timeout;

    private array $queryParams = [];

    private array $headers = [];

    private array $cookies = [];

    private array $body = [];
    private array $files = [];

    private array $chain = [];

    private string $bodyFormat; // 'json', 'form', 'multipart'
    private string $fullUrl;

    private Log $log;

    private array $types = [
        GetRequest::class => 'get',
        PostRequest::class => 'post',
    ];

    public function __construct(readonly private AbstractRequest $clientRequest)
    {

        $this->validate();

        $this->log = new Log();

        $this->setQueryParams();
        $this->setBodyParams();
        $this->setChain();

//        $this->attempts = $this->clientRequest->getAttempts();
//        $this->remainingOfAttempts = $this->attempts;
        $this->url = $this->clientRequest->getUrl();
        $this->fullUrl = $this->getUrlWithQueryParams();
        $this->headers = $this->clientRequest->getClientDTO()->getHeaders();
        $this->bodyFormat = $this->clientRequest->getBodyFormat() ?: $this->clientRequest->getClientDTO()->getBodyFormat() ?: RequestOptions::JSON;;
        $this->timeout = $this->clientRequest->getTimeout() ?: $this->clientRequest->getClientDTO()->getTimeout();


    }

    public function validate()
    {
        $validator = Validator::make($this->clientRequest->toArray(), $this->clientRequest::rules());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function log():Log
    {
        return $this->log;
    }

    /**
     * Выполнить запрос
     */
    public function send(bool $reset = false): PromiseInterface|Response
    {
//        if ($reset) {
//            $this->remainingOfAttempts = $this->attempts;
//        }
//
//        if ($this->remainingOfAttempts() !== $this->getAttempts()) {
//            $this->log->add("Wait ({$this->getClientRequest()->getAttemptDelay()}ms)...");
//            usleep($this->getClientRequest()->getAttemptDelay() * 1000);
//        }
//
//        throw_if($this->remainingOfAttempts < 0, new RuntimeException("Unforeseen call. Attempts out of range."));
//
//        $this->log->add("Attempt {$this->attempt()}");
//
//        $this->remainingOfAttempts -= 1;

        return match ($this->getMethod($this->clientRequest)) {
            'get' => $this->get(),
            'post' => $this->post(),
            default => throw new InvalidArgumentException("Unsupported request type."),
        };
    }

    private function setChain(): void
    {
        $this->chain = array_filter([
            $this->getClientRequest()->getClientDTO(),
            $this->getClientRequest()->getResource(),
            $this->clientRequest,
        ]);
    }

    public function getChain(): array
    {
        return $this->chain;
    }

    public function getAttempts(): int
    {
        return $this->clientRequest->getAttempts();
    }

//    public function canAttempt(): bool
//    {
//        return $this->remainingOfAttempts > 0;
//    }
//
//    public function attempt(): int
//    {
//        return ($this->attempts - $this->remainingOfAttempts) + 1;
//    }
//
//    public function remainingOfAttempts(): int
//    {
//        return $this->remainingOfAttempts;
//    }


    public function getClientRequest(): AbstractRequest
    {
        return $this->clientRequest;
    }

    private function get(): PromiseInterface|Response
    {
        $request = Http::withHeaders($this->headers);

        if (!empty($this->cookies)) {
            $request = $request->withCookies($this->cookies, parse_url($this->url, PHP_URL_HOST));
        }

        return $request->get($this->fullUrl);
    }

    private function post(): PromiseInterface|Response
    {
        $request = Http::withHeaders($this->headers);

        $request->timeout($this->timeout);

        if (!empty($this->cookies)) {
            $request = $request->withCookies($this->cookies, parse_url($this->url, PHP_URL_HOST));
        }

        return match ($this->bodyFormat) {
            RequestOptions::JSON => $request->post($this->fullUrl, $this->body),
            RequestOptions::FORM_PARAMS => $request->asForm()->post($this->fullUrl, $this->body),
            RequestOptions::MULTIPART => $request->asMultipart()->post($this->fullUrl, array_merge($this->body, $this->files)),
            default => throw new InvalidArgumentException("Unsupported content type: $this->bodyFormat"),
        };
    }

    private function setBodyParams(): void
    {
        $this->body = array_merge(
            $this->clientRequest->bodyParams(),
        );
    }

    private function setQueryParams(): void
    {
        $this->queryParams = array_merge(
            $this->clientRequest->queryParams(),
            $this->clientRequest->getQueryParams(),
            $this->clientRequest->getResource()->getQueryParams(),
            $this->clientRequest->getClientDTO()?->getQueryParams()
        );
    }

    public function getUrlWithQueryParams(): string
    {
        $url =  $this->url;

        $result = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $property = $matches[1];
            return property_exists($this->getClientRequest(), $property) ? $this->getClientRequest()->$property : $matches[0];
        }, $url);

        return  $result . RequestHelper::getInstance()->makeQueryString($this->queryParams);
    }

    private function getMethod(AbstractRequest $abstractRequest): string
    {
        return $this->types[get_parent_class($abstractRequest)];
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'timeout' => $this->timeout,
            'queryParams' => $this->queryParams,
            'headers' => $this->headers,
            'cookies' => $this->cookies,
            'body' => $this->body,
            'files' => $this->files,
            'bodyFormat' => $this->bodyFormat,
            'fullUrl' => $this->fullUrl,
        ];
    }
}
