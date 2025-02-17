<?php

namespace Brahmic\ClientDTO\Traits;

trait BodyFormat
{
    private ?string $bodyFormat = null;    //one of RequestOptions


    public function setBodyFormat(string $type): static
    {
        $this->bodyFormat = $type;

        return $this;
    }

    public function getBodyFormat(): ?string
    {
        return $this->bodyFormat;
    }


}