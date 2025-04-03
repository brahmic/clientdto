<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;

/**
 * Атрибут устанавливается на case в перечислениях,
 * установленное значение будет использовано при преобразовании в массив.
 */
#[Attribute]
class MapCaseOutputValue
{
    public function __construct(
        public ?string $output = null
    )
    {
    }
}
