<?php

namespace Brahmic\ClientDTO\Casts;

use Bezopasno\IrbisClient\Resources\Person\DTO\Courts\Arbitrage\CasePerson;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class CastToObject implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
dd(123);
        /** @var Data $class */
        $class = $property->type->dataClass;
dd($class);
        return CasePerson::validateAndCreate($properties);
    }
}
