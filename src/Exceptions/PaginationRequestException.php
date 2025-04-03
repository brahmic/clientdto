<?php

namespace Brahmic\ClientDTO\Exceptions;

use Exception;
use Illuminate\Support\Collection;

class PaginationRequestException extends Exception
{

    public function __construct($message, protected Collection $failed)
    {
        parent::__construct($message, 502);
    }

    public function getFailed(): Collection
    {
        return $this->failed;
    }
}
