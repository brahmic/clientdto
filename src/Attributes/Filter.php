<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Filter
{
    public function __construct(public string|int $output)
    {
    }
}
