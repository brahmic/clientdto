<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;
use Brahmic\ClientDTO\Support\Data;

#[Attribute]
class CollectionOf
{
    /** @var string|Data  */
    public string $class;

    public function __construct(
         string $class,
    )
    {
        $this->class = $class;
    }
}
