<?php

namespace Brahmic\ClientDTO\Exceptions;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Exception;

class PreflightRequestException extends Exception
{

    public function __construct($message, protected ?ClientResponseInterface $clientResponse = null)
    {
        parent::__construct($message, 502);
    }

    public function getClientResponse(): ?ClientResponseInterface
    {
        return $this->clientResponse;
    }
}
