<?php

namespace Brahmic\ClientDTO\Contracts;


interface WrappedDtoInterface
{
    public static function setWrapped(string $class): void;
}
