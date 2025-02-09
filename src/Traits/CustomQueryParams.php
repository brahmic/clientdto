<?php

namespace Brahmic\ClientDTO\Traits;

trait CustomQueryParams
{
    private array $customQueryParams = [];


    public function getCustomQueryParams(): array
    {
        return $this->customQueryParams;
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
        $this->customQueryParams[$key] = $value;

        return $this;
    }

//    /**
//     * Возвращает новый (!) объект с добавленным параметром (иммутабельный вариант).
//     *
//     * @param string $key
//     * @param string $value
//     * @return $this
//     */
//    public function withQueryParam(string $key, string $value): static
//    {
//        $new = clone $this;
//        $new->queryParams[$key] = $value;
//
//        return $new;
//    }


    /**
     * Прямо указывает, что старое значение будет дополнено новым (например, через запятую), что подходит для случаев, когда вы хотите собрать несколько значений в одном параметре.
     * Пример: ?tags=php,laravel,web (множество значений, объединенные в строку)
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function appendQueryParam(string $key, string $value): static
    {
        // Если параметр уже существует, добавляем новое значение через запятую
        if (isset($this->customQueryParams[$key])) {
            $this->customQueryParams[$key] .= ',' . $value;
        } else {
            // Если параметра нет, создаем новый
            $this->customQueryParams[$key] = $value;
        }

        return $this;
    }


    /**
     * Добавляет параметры, но обычно оставляет их как отдельные элементы (массив значений с одинаковым ключом).
     * Полезен в случаях, когда параметры должны накапливаться, а не заменять друг друга
     * Пример: ?tags=php&tags=laravel&tags=web (множество значений с одинаковым ключом)
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function attachQueryParam(string $key, string $value): static
    {
        if (!isset($this->customQueryParams[$key])) {
            $this->customQueryParams[$key] = [];
        }

        // Добавляем новое значение в массив (не перезаписываем)
        $this->customQueryParams[$key][] = $value;

        return $this;
    }

}
