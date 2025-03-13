<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Support\Data;

interface ClientResponseInterface
{

    public function resolved(): mixed;

    public function toArray(): array;
}
