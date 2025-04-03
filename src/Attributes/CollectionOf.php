<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;
use Brahmic\ClientDTO\Support\Data;

#[Attribute]
class CollectionOf
{
    /** @var string|Data  */
    public string $class;

    public ?string $filedName;

    public function __construct(
         string $class,
         ?string $filedName = null,
    )
    {
        $this->class = $class;
        $this->filedName = $filedName;
    }
}
