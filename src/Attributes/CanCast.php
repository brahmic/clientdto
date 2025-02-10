<?php

namespace Brahmic\ClientDTO\Attributes;

use Spatie\LaravelData\Casts\Cast;

interface CanCast
{
    public function get(): Cast;
}