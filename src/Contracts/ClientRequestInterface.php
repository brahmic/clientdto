<?php

namespace Brahmic\ClientDTO\Contracts;


use Spatie\LaravelData\Data;

interface ClientRequestInterface
{

    public static function getDtoClass(): string|Data;
    public function toArray(): array;
}
