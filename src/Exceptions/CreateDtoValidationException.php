<?php

namespace Brahmic\ClientDTO\Exceptions;

use Illuminate\Http\Client\Response;
use Exception;
use Illuminate\Validation\ValidationException;

class CreateDtoValidationException extends ValidationException
{
    protected ?string $class = null;

    protected ?Response $laravelResponse = null;

    public function __construct($validator, $response = null, $errorBag = 'default')
    {
        $this->laravelResponse = $response;

        parent::__construct($validator, $response, $errorBag);
    }

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }
    public function getResponse(): Response
    {
        return $this->laravelResponse;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }
}
