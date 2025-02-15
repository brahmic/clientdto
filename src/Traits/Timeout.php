<?php

namespace Brahmic\ClientDTO\Traits;

trait Timeout
{
    protected ?int $timeout = null;


    public function getTimeout(): int
    {
        return $this->timeout ?: $this->getClientDTO()->getTimeout();
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

}