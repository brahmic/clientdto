<?php

namespace Brahmic\ClientDTO\ResourceScanner;

use Bezopasno\IrbisClient\Support\Attributes\Volume;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionMethod;

readonly class Context
{
    public function __construct(
        public ?ReflectionMethod $reflectionMethod = null,
        public ?Collection       $chain = null,
        public ?string           $resourceClass = null,
        public ?string           $class = null,
        public ?Volume           $volume = null,
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

    /**
     * Returns a collection of instantiated attributes from the reflection method.
     *
     * @return Collection
     */
    public function getClassAttributes(): Collection
    {
        $reflectionClass = new \ReflectionClass($this->class);

        return collect($reflectionClass->getAttributes())
            ->map(fn(ReflectionAttribute $attr) => $attr->newInstance());
    }

}
