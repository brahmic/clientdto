<?php

namespace Brahmic\ClientDTO\Contracts;


use Brahmic\ClientDTO\Response\ClientResponse;
use Spatie\LaravelData\Data;

interface ClientRequestInterface
{
    // Count of additional attempts

    // Transformation before of the DTO creating
    //public function transforming(mixed $data): mixed

    public static function getDtoClass(): null|string|Data;

    public function toArray(): array;

    public function send(): ClientResponseInterface|ClientResponse;
}
