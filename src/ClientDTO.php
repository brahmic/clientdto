<?php

namespace Brahmic\ClientDTO;

use Brahmic\ClientDTO\Traits\CustomQueryParams;

class ClientDTO
{

    use CustomQueryParams;

    private ?string $baseUrl = null;

    private int $timeout = 60;

    private bool $debug = false;

    private array $headers = [];

    private ?string $requestBodyType = null;


    /**
     * Группа параметров, таких как body, json, form_params и multipart, относится к параметрам,
     * которые отвечают за формирование тела запроса (request body) в Guzzle. Эти параметры используются
     * для отправки данных в теле HTTP-запроса. В Guzzle эти параметры обычно передаются через ключи
     * в массиве опций, которые передаются в метод запроса.
     *
     * todo Каждый запрос использует по умолчанию RequestOptions::FORM_DATA, однако это поведение можно
     * переопределить (в порядке увеличения приоритета) и устанавливает поведение всех POST запросов,
     * ЕСЛИ в нём непосредственно не сказано иное.
     *
     * Для конкретного запроса непосредственно в его классе можно установить иное значение,
     * и это будет иметь высший приоритет.
     *
     * @param string $type
     * @return $this
     */
    public function setRequestBodyType(string $type): static
    {
        $this->requestBodyType = $type;

        return $this;
    }

    public function getRequestBodyType(): ?string
    {
        return $this->requestBodyType;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Таймаут будет использован ВО ВСЕХ запросах, если в конкретном запросе не определено иное.
     *
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function removeHeader(string $key): static
    {
        if (isset($this->headers[$key])) {
            unset($this->headers[$key]);
        }

        return $this;
    }

    public function addHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Заголовки будут использованы ВО ВСЕХ запросах.
     *
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    public function getBaseUrl(?string $uri = ''): ?string
    {
        return $uri ? $this->baseUrl . $uri : $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }
}
