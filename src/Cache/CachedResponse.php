<?php

namespace Brahmic\ClientDTO\Cache;

/**
 * Запись в кеше с метаинформацией
 * 
 * Содержит кешированные данные и информацию о типе кеширования
 */
class CachedResponse
{
    /**
     * @param mixed $resolved Кешированные данные (DTO объект или JSON строка)
     * @param bool $isRaw Тип кеша: true = RAW данные, false = DTO объект
     * @param string|null $rawResponse Сырой HTTP ответ (для метода raw())
     */
    public function __construct(
        public readonly mixed $resolved,
        public readonly bool $isRaw,
        public readonly ?string $rawResponse = null
    ) {}

    /**
     * Создать запись для RAW кеша
     */
    public static function raw(mixed $resolvedData, string $rawResponse): self
    {
        return new self(
            resolved: $resolvedData,
            isRaw: true, 
            rawResponse: $rawResponse
        );
    }

    /**
     * Создать запись для DTO кеша
     */
    public static function dto(mixed $resolvedData, ?string $rawResponse = null): self
    {
        return new self(
            resolved: $resolvedData,
            isRaw: false,
            rawResponse: $rawResponse
        );
    }
}
