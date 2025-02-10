<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Rename
{
    public function __construct(public string|int $output)
    {
    }
}
