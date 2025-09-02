<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Cacheable
{
    public function __construct(
        /**
         * Время жизни кеша в секундах
         * null - использовать TTL по умолчанию
         */
        public readonly ?int $ttl = null,
        
        /**
         * Теги для группировки кеша
         * Используются для групповой инвалидации
         */
        public readonly array $tags = [],
        
        /**
         * Включено ли кеширование для данного запроса
         */
        public readonly bool $enabled = true
    ) {}
}
