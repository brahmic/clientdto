<?php

namespace Brahmic\ClientDTO\Builders;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GetRequestBuilder
{
    protected string $url;

    protected array $queryParams = [];

    protected array $headers = [];

    protected array $cookies = [];

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Добавить Query-параметры (?key=value)
     */
    public function withQuery(array $queryParams): self
    {
        $this->queryParams = array_merge($this->queryParams, $queryParams);

        return $this;
    }

    /**
     * Добавить заголовки (Headers)
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Добавить Cookie
     */
    public function withCookies(array $cookies): self
    {
        $this->cookies = array_merge($this->cookies, $cookies);

        return $this;
    }

    /**
     * Выполнить GET-запрос
     */
    public function send(): Response
    {
        $fullUrl = $this->url . (!empty($this->queryParams) ? '?' . http_build_query($this->queryParams) : '');

        $request = Http::withHeaders($this->headers);

        if (!empty($this->cookies)) {
            $request = $request->withCookies($this->cookies, parse_url($this->url, PHP_URL_HOST));
        }

        return $request->get($fullUrl);
    }
}
