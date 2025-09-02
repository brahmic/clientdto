<?php

namespace Brahmic\ClientDTO\Cache;

/**
 * Конфигурация кеширования для ClientDTO
 */
class CacheConfig
{
    /**
     * TTL по умолчанию для кеша запросов (в секундах)
     * null - использует настройки Laravel Cache
     */
    public const DEFAULT_TTL = null;

    /**
     * Префиксы ключей кеша
     */
    public const PREFIX_REQUEST = 'clientdto.request.';
    public const PREFIX_GROUPED = 'clientdto.grouped.';

    /**
     * Получить TTL по умолчанию
     */
    public static function getDefaultTtl(): ?int
    {
        return config('clientdto.cache.default_ttl', self::DEFAULT_TTL);
    }

    /**
     * Получить префикс ключа для обычных запросов
     */
    public static function getRequestPrefix(): string
    {
        return config('clientdto.cache.request_prefix', self::PREFIX_REQUEST);
    }

    /**
     * Получить префикс ключа для групповых запросов
     */
    public static function getGroupedPrefix(): string
    {
        return config('clientdto.cache.grouped_prefix', self::PREFIX_GROUPED);
    }
}
