<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Requests\PostRequest;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PostRequestBuilder
{
    protected string $url;

    protected int $timeout;

    protected array $queryParams = [];

    protected array $headers = [];

    protected array $cookies = [];

    protected array $body = [];

    protected array $files = [];

    protected string $contentType = RequestOptions::JSON; // 'json', 'form', 'multipart'

    public function __construct(public PostRequest $postRequest)
    {
        $this->setQueryParams();
        $this->setBodyParams();

        $this->url = $this->postRequest->getUrl();
        $this->contentType = $this->postRequest->getBodyFormat();
        $this->timeout = $this->postRequest->getTimeout() ?: $this->postRequest->getClientDTO()->getTimeout();
    }

    private function setBodyParams(): void
    {
        $this->body = array_merge(
            $this->postRequest->bodyParams(),
        );
    }

    private function setQueryParams(): void
    {
        $this->queryParams = array_merge(
        // указанные в классе запроса если метод переопределён или на основе свойств класса
            $this->postRequest->queryParams(),
            // параметры, которые могли быть добавлены динамически в классе запроса через другие методы
            $this->postRequest->getQueryParams(),
            // параметры, которые были указаны в клиенте
            $this->postRequest->getClientDTO()->getQueryParams()
        );
    }

    /**
     * Выполнить POST-запрос
     */
    public function send(): Response
    {
        $fullUrl = $this->url . (!empty($this->queryParams) ? '?' . http_build_query($this->queryParams) : '');


        $request = Http::withHeaders($this->headers);

        $request->timeout($this->postRequest->getTimeout());

        if (!empty($this->cookies)) {
            $request = $request->withCookies($this->cookies, parse_url($this->url, PHP_URL_HOST));
        }

        return match ($this->contentType) {
            RequestOptions::JSON => $request->post($fullUrl, $this->body),
            RequestOptions::FORM_PARAMS => $request->asForm()->post($fullUrl, $this->body),
            RequestOptions::MULTIPART => $request->asMultipart()->post($fullUrl, array_merge($this->body, $this->files)),
            default => throw new \InvalidArgumentException("Unsupported content type: $this->contentType"),
        };
    }
}
