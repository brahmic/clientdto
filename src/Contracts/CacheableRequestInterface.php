<?php

namespace Brahmic\ClientDTO\Contracts;

interface CacheableRequestInterface 
{
    /**
     * Время жизни кеша в секундах
     * null - использовать TTL по умолчанию
     */
    public function getCacheTtl(): ?int;

    /**
     * Теги для группировки кеша
     * Используются для групповой инвалидации
     */
    public function getCacheTags(): array;

    /**
     * Должен ли данный результат кешироваться
     * @param mixed $resolved Результат после обработки
     */
    public function shouldCache(mixed $resolved): bool;
}
