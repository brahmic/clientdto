<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

/**
 * Атрибут для управления кешированием запросов
 * 
 * Примеры использования:
 * #[Cachable] - включить кеширование
 * #[Cachable(true)] - включить кеширование  
 * #[Cachable(false)] - отключить кеширование
 * #[Cachable(true, 3600)] - кеширование на 1 час
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Cachable
{
    /**
     * @param bool $enabled Включено ли кеширование
     * @param int|null $ttl Время жизни кеша в секундах (null = без ограничений)
     */
    public function __construct(
        public bool $enabled = true,
        public ?int $ttl = null
    ) {}
}
