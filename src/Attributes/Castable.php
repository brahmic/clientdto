<?php

namespace Brahmic\ClientDTO\Attributes;

use Spatie\LaravelData\Casts\Cast;

interface Castable
{
    public function value(): mixed;
}
