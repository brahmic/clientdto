<?php

namespace Brahmic\ClientDTO\Contracts;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PostRequestBuilder
{
    protected string $url;

    protected array $queryParams = [];

    protected array $headers = [];

    protected array $cookies = [];

    protected array $body = [];

    protected array $files = [];

    protected string $contentType = RequestOptions::JSON; // 'json', 'form', 'multipart'

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Добавить Query параметры (?key=value)
     */
    public function withQuery(array $queryParams): self
    {
        $this->queryParams = array_merge($this->queryParams, $queryParams);

        return $this;
    }

    /**
     * Добавить заголовки
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
     * Добавить данные в тело запроса (JSON, form-data, x-www-form-urlencoded)
     */
    public function withBody(array $body, string $type = RequestOptions::JSON): self
    {
        $this->body = array_merge($this->body, $body);
        $this->contentType = $type;

        return $this;
    }

    /**
     * Добавить файлы (multipart/form-data)
     */
    public function withFiles(array $files): self
    {
        foreach ($files as $key => $file) {
            $this->files[$key] = fopen($file, 'r'); // Открываем файлы для передачи
        }
        $this->contentType = RequestOptions::MULTIPART;

        return $this;
    }

    /**
     * Выполнить POST-запрос
     */
    public function send(): Response
    {
        $fullUrl = $this->url . (!empty($this->queryParams) ? '?' . http_build_query($this->queryParams) : '');

        $request = Http::withHeaders($this->headers);

        if (!empty($this->cookies)) {
            $request = $request->withCookies($this->cookies, parse_url($this->url, PHP_URL_HOST));
        }

        // Определяем способ передачи тела запроса
        return match ($this->contentType) {
            RequestOptions::JSON => $request->post($fullUrl, $this->body),
            RequestOptions::FORM_PARAMS => $request->asForm()->post($fullUrl, $this->body),
            RequestOptions::MULTIPART => $request->asMultipart()->post($fullUrl, array_merge($this->body, $this->files)),
            default => throw new \InvalidArgumentException("Неподдерживаемый тип контента: $this->contentType"),
        };
    }
}
