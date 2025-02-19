<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;

class ClientResponse implements ClientResponseInterface
{

    public function isAttemptNeeded(mixed $data): bool
    {
        return true;
    }
}
