<?php

namespace Brahmic\ClientDTO\ResourceScanner;

use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionMethod;

readonly class Context
{
    public function __construct(
        public ?ReflectionMethod $reflectionMethod = null,
        public ?Collection       $chain = null,
        public ?string           $resourceClass = null,
    )
    {

    }


    /**
     * Returns a collection of instantiated attributes from the reflection method.
     *
     * @return Collection
     */
    public function getMethodAttributes(): Collection
    {
        if (!$this->reflectionMethod) {
            return collect();
        }

        return collect($this->reflectionMethod->getAttributes())
            ->map(fn(ReflectionAttribute $attr) => $attr->newInstance());
    }

}
