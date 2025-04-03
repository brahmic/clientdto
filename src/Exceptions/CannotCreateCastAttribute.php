<?php

namespace Brahmic\ClientDTO\Exceptions;

use Brahmic\ClientDTO\Attributes\Cast;
use Brahmic\ClientDTO\Attributes\Castable;
use Exception;

class CannotCreateCastAttribute extends Exception
{
    public static function notACast(): self
    {
        $cast = Cast::class;

        return new self("Cast attribute needs a cast that implements `{$cast}`");
    }

    public static function notACastable(): self
    {
        $cast = Castable::class;

        return new self("Castable attribute needs a class that implements `{$cast}`");
    }
}
