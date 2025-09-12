<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

/**
 * Атрибут для управления кешированием запросов
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Cacheable
{
    public function __construct(
        public readonly bool $enabled = true,
        public readonly ?int $ttl = null
    ) {}
}
