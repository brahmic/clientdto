<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\Attributes\Filter;
use Spatie\LaravelData\Attributes\MapOutputName;

readonly class ProcessedProperty
{


    public function __construct(public string $key, public mixed $value, public bool $needsRemove )
    {

    }

    public static function make(object $attribute, $value):self
    {
        $newValue = $value;

        // Пример обработки атрибута
        if ($attribute instanceof MapOutputName) {
            $newKey = $attribute->output;
        }

        if ($attribute instanceof Filter) {
            if (!is_string($value)) {
                throw new \Exception("The field must be a string.");
            }
        }

        $key = 'sdfsdf';
        $remove = true;

        return new self($newValue, $newKey, $remove);
    }
}
