<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\Attributes\Filter;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapOutputName;

readonly class RequestDataProperty
{

    public function __construct(
        public readonly string $name,
        public mixed           $value,
        public readonly bool $hidden,
        public readonly bool $hideFromBody,
        public readonly bool $hideFromQueryStr,
    )
    {
    }


}
