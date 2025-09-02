<?php

namespace Brahmic\ClientDTO\Cache;

use Brahmic\ClientDTO\Response\FileResponse;
use Brahmic\ClientDTO\Support\Data;

class CacheDataSerializer
{
    /**
     * Сериализовать данные для кеширования
     * @param mixed $data Данные для сериализации
     * @return string Сериализованные данные
     */
    public function serialize(mixed $data): string
    {
        // FileResponse не кешируем
        if ($data instanceof FileResponse) {
            throw new \InvalidArgumentException('FileResponse objects cannot be cached');
        }

        // Data objects (DTO) используют стандартную сериализацию PHP
        if ($data instanceof Data) {
            return serialize($data);
        }

        // Коллекции и массивы
        if (is_array($data) || $data instanceof \Illuminate\Support\Collection) {
            return serialize($data);
        }

        // Остальные данные
        return serialize($data);
    }

    /**
     * Десериализовать данные из кеша
     * @param string $serializedData Сериализованные данные
     * @return mixed Десериализованные данные
     */
    public function unserialize(string $serializedData): mixed
    {
        return unserialize($serializedData);
    }

    /**
     * Проверить, можно ли кешировать данные
     */
    public function canCache(mixed $data): bool
    {
        // FileResponse не кешируем
        if ($data instanceof FileResponse) {
            return false;
        }

        // Null не кешируем
        if (is_null($data)) {
            return false;
        }

        return true;
    }
}
