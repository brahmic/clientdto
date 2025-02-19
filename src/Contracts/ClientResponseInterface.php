<?php

namespace Brahmic\ClientDTO\Contracts;

interface ClientResponseInterface
{

    public function isAttemptNeeded(mixed $data): bool;
}
