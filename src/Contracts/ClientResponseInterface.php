<?php

namespace Brahmic\ClientDTO\Contracts;


use Brahmic\ClientDTO\Response\RequestResult;

interface ClientResponseInterface
{

    public function resolved(): mixed;

    public function getMessage(): ?string;

    public function hasError(): bool;

    public function toArray(): array;
}
