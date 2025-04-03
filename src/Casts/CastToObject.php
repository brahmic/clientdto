<?php

namespace Brahmic\ClientDTO\Casts;

use Brahmic\ClientDTO\Support\Data;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class CastToObject implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        /** @var Data $class */
        $class = $property->type->dataClass;

        return $class::from($properties);
    }
}
