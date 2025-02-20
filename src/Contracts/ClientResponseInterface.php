<?php

namespace Brahmic\ClientDTO\Contracts;

use Spatie\LaravelData\Data;

interface ClientResponseInterface
{

    public function isAttemptNeeded(): bool;
}
