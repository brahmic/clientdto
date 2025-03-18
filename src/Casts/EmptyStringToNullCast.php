<?php

namespace Brahmic\ClientDTO\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class EmptyStringToNullCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        // Если значение — пустая строка, возвращаем null
        return $value === '' ? null : $value;
    }
}
