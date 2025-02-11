<?php

namespace Brahmic\ClientDTO\Support;


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
