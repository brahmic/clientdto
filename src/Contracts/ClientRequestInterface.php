<?php

namespace Brahmic\ClientDTO\Contracts;


use Spatie\LaravelData\Data;

interface ClientRequestInterface
{
    // Count of additional attempts
    //public function isAttemptNeeded(mixed $data): bool;

    // Transformation before of the DTO creating
    //public function transforming(mixed $data): mixed

    public static function getDtoClass(): null|string|Data;
    public function getResource(): AbstractResource;

    public function toArray(): array;
}
