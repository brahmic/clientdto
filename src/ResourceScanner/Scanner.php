<?php

namespace Brahmic\ClientDTO\ResourceScanner;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\AbstractResource;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

class Scanner
{

    public function scanClient(string $rootClass): ResourceMap
    {
        $resourceTree = [];
        $requestParents = [];

        // Retrieving all methods of the root class
        $methods = get_class_methods($rootClass);

        // Iterating through all methods of the root class
        foreach ($methods as $method) {
            $reflectionMethod = new ReflectionMethod($rootClass, $method);

            // Skipping private or static methods
            if (!$reflectionMethod->isPublic() || $reflectionMethod->isStatic()) {
                continue;
            }

            // Getting the return type of the method
            $returnType = $reflectionMethod->getReturnType();
            if ($returnType instanceof ReflectionNamedType) {

                $returnClass = $returnType->getName();

                // If the method returns a descendant of AbstractResource
                if (is_subclass_of($returnClass, AbstractResource::class)) {
                    // For each method that returns an AbstractResource, call scanResource
                    $scannedMap = self::scanResource($returnClass);
                    // Merge resources and requests from the scanned map
                    $resourceTree += $scannedMap->resources;
                    $requestParents += $scannedMap->requests;
                }
            }
        }

        return new ResourceMap($rootClass, $resourceTree, $requestParents);
    }


    public function scanResource(string $resourceClass): ResourceMap
    {
        $resourceTree = [];
        $requestParents = [];

        $scan = function (string $class, array $path = []) use (&$resourceTree, &$requestParents, &$scan) {
            if (!is_subclass_of($class, AbstractResource::class)) {
                throw new InvalidArgumentException("$class must be extend of AbstractResource");
            }

            $currentPath = array_merge($path, [$class]);
            $methods = get_class_methods($class);

            $context = new Context(
                chain: collect($currentPath),
                resourceClass: $class,
            );

            $resourceTree[$class] = [
                ...$class::declare($context),
                'resources' => [],
                'requests' => [],
            ];

            foreach ($methods as $method) {
                $reflectionMethod = new ReflectionMethod($class, $method);
                $returnType = $reflectionMethod->getReturnType();
                $returnClass = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;

                if ($returnClass && class_exists($returnClass)) {

                    if (is_subclass_of($returnClass, AbstractResource::class)) {

                        // Add nested resource
                        $resourceTree[$class]['resources'][] = $returnClass;
                        $scan($returnClass, $currentPath); // Recursive scan

                    } elseif (is_subclass_of($returnClass, AbstractRequest::class)) {

                        // Add request and save parent class
                        $resourceTree[$class]['requests'][$returnClass] = $returnClass;

                        $context = new Context(
                            reflectionMethod: $reflectionMethod,
                            chain: collect($currentPath),
                            resourceClass: $class,
                        );

                        $requestParents[$returnClass] = [
                            ...$returnClass::declare($context),
                            'resources' => $currentPath,
                        ];
                    }
                }
            }
        };

        $scan($resourceClass);

        return new ResourceMap($resourceClass, $resourceTree, $requestParents);
    }

}
