<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MapName
{
    public function __construct(public string|int $output)
    {
    }
}
