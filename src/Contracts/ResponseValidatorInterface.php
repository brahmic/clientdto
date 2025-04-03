<?php

namespace Brahmic\ClientDTO\Contracts;

interface ResponseValidatorInterface
{
    public function validate(array $data): bool;
}