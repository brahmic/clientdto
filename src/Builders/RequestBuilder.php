<?php

namespace Brahmic\ClientDTO\Builders;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Support\RequestHelper;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RequestBuilder
{
    private string $url;

    private int $timeout;

    private array $queryParams = [];

    private array $headers = [];

    private array $cookies = [];

    private array $body = [];

    private array $files = [];

    private string $bodyFormat; // 'json', 'form', 'multipart'

    private string $fullUrl;

    private array $types = [
        GetRequest::class => 'get',
        PostRequest::class => 'post',
    ];

    public function __construct(public AbstractRequest $clientRequest)
    {
        $this->setQueryParams();
        $this->setBodyParams();

        $this->url = $this->clientRequest->getUrl();
        $this->fullUrl = $this->getUrlWithQueryParams();
        $this->headers = $this->clientRequest->getClientDTO()->getHeaders();
        $this->bodyFormat = $this->clientRequest->getBodyFormat() ?: $this->clientRequest->getClientDTO()->getBodyFormat() ?: RequestOptions::JSON;;
        $this->timeout = $this->clientRequest->getTimeout() ?: $this->clientRequest->getClientDTO()->getTimeout();
    }

    /**
     * Выполнить POST-запрос
     */
    public function send(): Response
    {
        return match ($this->getMethod($this->clientRequest)) {
            'get' => $this->get(),
            'post' => $this->post(),
            default => throw new \InvalidArgumentException("Unsupported request type."),
        };
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
            default => throw new \InvalidArgumentException("Unsupported content type: $this->bodyFormat"),
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

    private function getUrlWithQueryParams(): string
    {
        return $this->url . RequestHelper::getInstance()->makeQueryString($this->queryParams);
    }

    private function getMethod(AbstractRequest $abstractRequest): string
    {
        return $this->types[get_parent_class($abstractRequest)];
    }
}
