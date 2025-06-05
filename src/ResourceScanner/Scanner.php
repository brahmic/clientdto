<?php

namespace Brahmic\ClientDTO\ResourceScanner;

use Bezopasno\IrbisClient\Support\Attributes\Volume;
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
                } elseif (is_subclass_of($returnClass, AbstractRequest::class)) {

                    $volume = $this->getEffectiveVolume($reflectionMethod, $returnClass, $this->getClassVolume($rootClass));

                    $context = new Context(
                        reflectionMethod: $reflectionMethod,
                        chain: collect([$rootClass]),
                        resourceClass: $rootClass,
                        class: $returnClass,
                        volume: $volume,
                    );

                    $resourceTree[$returnClass] = [
                        ...$returnClass::declare($context),
                        'resources' => [$rootClass],
                        'volume' => $volume?->toArray(),
                    ];
                }

            }
        }
//dump($requestParents);
        return new ResourceMap($rootClass, $resourceTree, $requestParents);
    }


    public function scanResource(string $resourceClass): ResourceMap
    {
        $resourceTree = [];
        $requestParents = [];

        $volume = $this->getClassVolume($resourceClass);

        $scan = function (string $class, array $path = [], $volume = null) use (&$resourceTree, &$requestParents, &$scan) {
            if (!is_subclass_of($class, AbstractResource::class)) {
                throw new InvalidArgumentException("$class must be extend of AbstractResource");
            }

            $currentPath = array_merge($path, [$class]);
            $methods = get_class_methods($class);

            $context = new Context(
                chain: collect($currentPath),
                resourceClass: $class,
                class: $class,
                volume: $volume,
            );

            $resourceTree[$class] = [
                ...$class::declare($context),
                'resources' => [],
                'requests' => [],
                'volume' => $volume?->toArray(),
            ];

            foreach ($methods as $method) {
                $reflectionMethod = new ReflectionMethod($class, $method);
                $returnType = $reflectionMethod->getReturnType();
                $returnClass = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;

                if ($returnClass && class_exists($returnClass)) {

                    if (is_subclass_of($returnClass, AbstractResource::class)) {

                        $effectiveVolume = $this->getEffectiveVolume($reflectionMethod, $returnClass, $volume);

                        $resourceTree[$class]['resources'][] = $returnClass;
                        $scan($returnClass, $currentPath, $effectiveVolume);

                    } elseif (is_subclass_of($returnClass, AbstractRequest::class)) {

                        $effectiveVolume = $this->getEffectiveVolume($reflectionMethod, $returnClass, $volume);

                        // Add request and save parent class
                        $resourceTree[$class]['requests'][$returnClass] = $returnClass;

                        $context = new Context(
                            reflectionMethod: $reflectionMethod,
                            chain: collect($currentPath),
                            resourceClass: $class,
                            class: $returnClass,
                            volume: $effectiveVolume,
                        );


                        $requestParents[$returnClass] = [
                            ...$returnClass::declare($context),
                            'resources' => $currentPath,
                            'volume' => $effectiveVolume?->toArray(),
                        ];
                    }
                }
            }
        };

        $scan($resourceClass, [], $volume);

        return new ResourceMap($resourceClass, $resourceTree, $requestParents);
    }

    private function getEffectiveVolume(ReflectionMethod $reflectionMethod, string $class, ?Volume $default): ?Volume
    {
        $methodVolume = $this->getMethodVolume($reflectionMethod);
        $classVolume = $this->getClassVolume($class);
        return $methodVolume ?? $classVolume ?? $default;
    }

    private function getClassVolume(string $class): ?Volume
    {
        // Создаем ReflectionProperty для свойства
        $reflectionProperty = new \ReflectionClass($class);

        // Получаем атрибуты свойства
        $attributes = $reflectionProperty->getAttributes(Volume::class);

        // Если атрибут найден, возвращаем его экземпляр
        if (!empty($attributes)) {
            return $attributes[0]->newInstance();
        }

        return null;
    }

    private function getMethodVolume($reflectionMethod): ?Volume
    {
        // Получаем атрибуты свойства
        $attributes = $reflectionMethod->getAttributes(Volume::class);

        // Если атрибут найден, возвращаем его экземпляр
        if (!empty($attributes)) {
            return $attributes[0]->newInstance();
        }

        return null;
    }
}
