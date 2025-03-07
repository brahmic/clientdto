<?php

namespace Brahmic\ClientDTO\Exceptions;

use Exception;
use Illuminate\Support\Collection;

class PaginationRequestException extends Exception
{

    public function __construct($message, protected Collection $errors)
    {
        parent::__construct($message, 502);
    }

    public function getErrors(): Collection
    {
        return $this->errors;
    }
}
