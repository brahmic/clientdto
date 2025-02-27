<?php

namespace Brahmic\ClientDTO\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;

class CreateDtoValidationException extends ValidationException
{
    protected ?string $class = null;

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }
}
