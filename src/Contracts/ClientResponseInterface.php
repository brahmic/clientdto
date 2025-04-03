<?php

namespace Brahmic\ClientDTO\Contracts;


interface ClientResponseInterface
{

    public function resolved(): mixed;

    public function getMessage(): ?string;

    public function hasError(): bool;

    public function toArray(): array;
}
