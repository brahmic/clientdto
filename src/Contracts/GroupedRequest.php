<?php

namespace Brahmic\ClientDTO\Contracts;

use Illuminate\Support\Collection;

interface GroupedRequest
{
    public function getRequestClasses(): Collection;
}
