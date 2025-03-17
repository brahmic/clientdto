<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;
use Brahmic\ClientDTO\Support\Data;

#[Attribute]
class ExtractInputFrom
{
    /** @var string|Data  */
    public string $filedName;

    public function __construct(
         string $class,
    )
    {
        $this->filedName = $class;
    }
}
