<?php

namespace Brahmic\ClientDTO\Traits;

use Brahmic\ClientDTO\Support\RequestHelper;
use Illuminate\Support\Collection;

trait QueryParams
{
    private array $queryParams = [];


    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Заменяет параметр. Подразумевает, что значение будет единственным для ключа.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function addQueryParam(string $key, string $value): static
    {
        $this->queryParams[$key] = $value;

        return $this;
    }

    /**
     * Прямо указывает, что старое значение будет дополнено новым (например, через запятую), что подходит для случаев, когда вы хотите собрать несколько значений в одном параметре.
     * Пример: ?tags=php,laravel,web (множество значений, объединенные в строку)
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function appendQueryParam(string $key, string $value): static
    {
        if (isset($this->queryParams[$key])) {
            $this->queryParams[$key] .= ',' . $value;
        } else {
            $this->queryParams[$key] = $value;
        }

        return $this;
    }


    /**
     * Добавляет параметры, но обычно оставляет их как отдельные элементы (массив значений с одинаковым ключом).
     * Полезен в случаях, когда параметры должны накапливаться, а не заменять друг друга
     * Example: ?tags=php&tags=laravel&tags=web (множество значений с одинаковым ключом)
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function attachQueryParam(string $key, string $value): static
    {
        if (!isset($this->queryParams[$key])) {
            $this->queryParams[$key] = [];
        }

        $this->queryParams[$key][] = $value;

        return $this;
    }

    public function getCustomQueryParamsAsString($flat = false, $hasQuestion = true): ?string
    {
        return RequestHelper::getInstance()->makeQueryString($this->getQueryParams(), $flat, $hasQuestion);
    }
}
