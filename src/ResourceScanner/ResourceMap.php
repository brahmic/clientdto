<?php

namespace Brahmic\ClientDTO\ResourceScanner;

use Illuminate\Support\Collection;

readonly class ResourceMap
{
    public function __construct(
        private string $rootClass,
        public array   $requests,
        public array   $resources,
    ) {

    }

    public function getRequestDeclaration(string $requestClass): array
    {
        return $this->requests[$requestClass];
    }

    public function getRootClass(): string
    {
        return $this->rootClass;
    }

    public function getRequestResources(string $requestClass): Collection
    {
        return collect($this->getRequestDeclaration($requestClass)['resources'] ?? []);
    }

    public function getAllRequestsForResource(string $resourceClass): Collection
    {
        if (!isset($this->resources[$resourceClass])) {
            return collect();
        }

        $requests = [];

        $traverse = function (string $class) use (&$traverse, &$requests) {
            if (!isset($this->resources[$class])) {
                return;
            }

            $requests = [...$requests, ...array_keys($this->resources[$class]['requests'] ?? [])];

            foreach ($this->resources[$class]['resources'] ?? [] as $subResource) {
                $traverse($subResource);
            }
        };

        $traverse($resourceClass);

        return collect(array_unique($requests));
    }


    public function allRequests(string $resourceClass): Collection
    {
        $requestClasses = $this->getAllRequestsForResource($resourceClass);

        return collect(array_intersect_key($this->requests, array_flip($requestClasses->toArray())));
    }
}

