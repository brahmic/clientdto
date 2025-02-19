<?php

namespace Brahmic\ClientDTO\Test\Provider;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;

class SomeClientResponse implements ClientResponseInterface
{

    public function isAttemptNeeded(ResponseDTO $data): bool
    {
        // TODO: Implement isAttemptNeeded() method.
    }
}
