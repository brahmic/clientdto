<?php

namespace Brahmic\ClientDTO\Requests;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ChainInterface;
use Brahmic\ClientDTO\Support\Log;
use Brahmic\ClientDTO\Support\RequestHelper;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ExecutiveRequest implements Arrayable
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

    public function __construct(readonly private AbstractRequest $clientRequest)
    {
        $clientRequest->validateRequest();

        $this->beforeExecute();

        $this->setQueryParams();
        $this->setBodyParams();

        $this->url = $this->clientRequest->getUrl();
        $this->fullUrl = $this->getUrlWithQueryParams();
        $this->headers = $this->clientRequest->getClientDTO()->getHeaders();
        $this->bodyFormat = $this->clientRequest->getBodyFormat() ?: $this->clientRequest->getClientDTO()->getBodyFormat() ?: RequestOptions::JSON;;
        $this->timeout = $this->clientRequest->getTimeout() ?: $this->clientRequest->getClientDTO()->getTimeout();
    }

    private function beforeExecute(): void
    {
        foreach ($this->clientRequest->getChain() as $chain) {
            if (method_exists($chain, 'beforeExecute')) {
                $chain->beforeExecute($this->clientRequest);
            }
        }
    }

    /**
     * @return PromiseInterface|Response
     * @throws \Exception
     */
    public function send(): PromiseInterface|Response
    {
        return match ($this->clientRequest->getMethod()) {
            'get' => $this->get(),
            'post' => $this->post(),
            default => throw new InvalidArgumentException("Unsupported request type."),
        };
    }

    public function getAttempts(): int
    {
        return $this->clientRequest->getAttempts();
    }

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
        //todo chain
        $this->body = array_merge(
            $this->clientRequest->bodyParams(),
        );
    }

    private function setQueryParams(): void
    {
        $chainQueryParams = $this->clientRequest->getChain()->reduce(function ($carry, ChainInterface $chain) {
            return array_merge($carry, $chain->getQueryParams());
        }, []);


        $this->queryParams = array_merge($chainQueryParams, $this->clientRequest->queryParams());
    }

    //todo helper
    public function getUrlWithQueryParams(): string
    {
        $url = $this->url;

        $result = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $property = $matches[1];
            if (property_exists($this->getClientRequest(), $property)) {
                $value = $this->getClientRequest()->$property;

                // Если значение является Enum, преобразуем его в строку
                if ($value instanceof \UnitEnum) {
                    return $value instanceof \BackedEnum ? $value->value : $value->name;
                }

                return $value;
            }
            return $matches[0];
        }, $url);

        return $result . RequestHelper::getInstance()->makeQueryString($this->queryParams, $this->getClientRequest()->isFlatQueryParams());
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
