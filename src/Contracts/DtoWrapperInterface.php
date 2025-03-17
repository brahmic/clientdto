<?php

namespace Brahmic\ClientDTO\Contracts;


interface DtoWrapperInterface
{
    public static function setDto(string $class): void;
}
