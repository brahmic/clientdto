<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\AbstractResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

class ClientResolver
{
    private static ?ClientResolver $instance = null;

    private array $clients = [];


    public static function registerClient(ClientDTO|string $clientDTO, bool $force = false): Collection
    {
        $resolver = self::getInstance();

        $clientDTOClass = is_object($clientDTO) ? $clientDTO::class : $clientDTO;

        $data = collect([
            'client' => $clientDTOClass,
            ...$resolver->createClientResourcesMap($clientDTOClass, $force),
        ]);

        $resolver->clients[$resolver->getKey($clientDTOClass)] = $data;
dd($data);
        return $data;
    }

    public static function resolveResource(string $resourceClass): ?AbstractResource
    {
        $resolver = self::getInstance();

        $data = $resolver->getData($resourceClass);

        if (array_key_exists('resource', $data) && $clientClass = $data['resource']) {
            return app($clientClass);
        }

        return null;
    }

    public static function resolve(string $resourceOrRequestClass): ClientDTO
    {
        $resolver = self::getInstance();

        $data = $resolver->getData($resourceOrRequestClass);
dd($data);
        return app($data['client']);
    }

    private function getData(string $class): array
    {
        $data = $this->getResourceMap($class)->get($class);

        if (!$data) {

            //try again, maybe it was renamed
            $this->registerClient($this->extractClassName($class), force: true);

            if (!$data = $this->getResourceMap($class)->get($class)) {

                // need to check $resourceClass namespace
                throw new \Exception("Can't resolve Resource or request with name {$class}.");
            }
        }

        return $data;
    }

    private function extractClassName(string $resourceClass): string
    {
        return $this->extractRegistered($resourceClass)['client'];
    }

    private function getResourceMap(string $resourceClass): Collection
    {
        return $this->extractRegistered($resourceClass);
    }

    private function extractRegistered(string $class): Collection
    {
        if (!$client = $this->clients[$this->getKey($class)]) {
            throw new \Exception("ClientDTO with {$this->getKey($class)} key not registered");
        }

        return $client;
    }

    private function getKey(string $class): string
    {
        $parts = explode('\\', $class);
        return implode('\\', array_slice($parts, 0, 2));
    }

    private function createClientResourcesMap(string $clientClass, bool $force = false): Collection
    {
        $cacheKey = 'clientdto.' . $this->getKey($clientClass);

//        if (Cache::has($cacheKey) && !$force) {
//            return Cache::get($cacheKey);
//        }
        $chain = [];
        //$this->buildCallChain($clientClass, $chain, );;

        $callMap = [];
        $callMap[$clientClass] = $this->buildCallMap($clientClass, $callMap);

dd($callMap);
        $collectedClientResources = $this->collectClientResources($clientClass);
dd($collectedClientResources);
        $resources = $collectedClientResources->mapWithKeys(function ($value, $key) {
            return [$key => ['client' => $value['client']]];
        });

        $requests = $collectedClientResources
            ->map(function ($resource, $key) {
                return $resource['requests']->mapWithKeys(function ($value) use ($key, $resource) {
                    return [$value => [
                        'client' => $resource['client'],
                        'resource' => $resource['resource'],
                    ]];
                });
            })
            ->flatMap(fn($items) => $items);

        $collected = $resources->merge($requests);

        Cache::put($cacheKey, $collected, Carbon::now()->addMonth());

        return $collected;
    }


    function buildCallMap(string $className, array &$callMap, array &$visited = []): ?array {
        if (in_array($className, $visited)) {

            return null;
        }

        $visited[] = $className;
        $reflection = new ReflectionClass($className);
        $methodsMap = [];

        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {
                $returnTypeName = $returnType->getName();

                if (is_subclass_of($returnTypeName, AbstractResource::class) || is_subclass_of($returnTypeName, AbstractRequest::class)) {
                    // Рекурсивно строим карту для возвращаемого типа
                    $methodsMap[$returnTypeName] = $this->buildCallMap($returnTypeName, $callMap, $visited);
                }
            }
        }

        if (is_subclass_of($className, AbstractRequest::class)) {
            return null;
        }

        return $methodsMap;
    }


    function invertCallMap(array $callMap): array {
        $invertedMap = [];

        foreach ($callMap as $requestClass => $chains) {
            foreach ($chains as $chain) {
                $currentLevel = &$invertedMap;

                // Проходим по цепочке и строим новую иерархию
                foreach ($chain as $class) {
                    if (!isset($currentLevel[$class])) {
                        $currentLevel[$class] = [];
                    }
                    $currentLevel = &$currentLevel[$class];
                }

                // Добавляем конечный элемент (класс Request)
                if (!isset($currentLevel[$requestClass])) {
                    $currentLevel[$requestClass] = [];
                }
            }
        }

        return $invertedMap;
    }

    function buildCallChain(string $className, array &$chain, array $currentPath = [], array &$visited = []): void {
        if (in_array($className, $visited)) {
            return;
        }

        $visited[] = $className;
        $reflection = new ReflectionClass($className);

        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {
                $returnTypeName = $returnType->getName();

                if (is_subclass_of($returnTypeName, AbstractResource::class)) {
                    // Добавляем текущий класс в цепочку и продолжаем рекурсию
                    $newPath = $currentPath;
                    $newPath[] = $returnTypeName;
                    $this->buildCallChain($returnTypeName, $chain, $newPath, $visited);
                } elseif (is_subclass_of($returnTypeName, AbstractRequest::class)) {
                    // Если возвращается AbstractRequest, сохраняем текущую цепочку
                    if (!isset($chain[$returnTypeName])) {
                        $chain[$returnTypeName] = [];
                    }
                    // Добавляем цепочку, начиная с Client и заканчивая AbstractRequest
                    $chain[$returnTypeName][] = $currentPath;//array_merge([Client::class], $currentPath);
                }
            }
        }
    }

    private function collectClientResources(string $clientClass): Collection
    {
        return $this->collectResources($clientClass, AbstractResource::class)
            ->mapWithKeys(function (string $resourceClass) use ($clientClass) {
                return [
                    $resourceClass => collect([
                        'client' => $clientClass,
                        'resource' => $resourceClass,
                        'requests' => $this->collectResources($resourceClass, AbstractRequest::class)
                    ])
                ];
            });
    }

    private function collectResources(string $sourceClass, string $targetClass): Collection
    {
        $reflectionClass = new ReflectionClass($sourceClass);

        $methods = $reflectionClass->getMethods();

        $result = collect();

        foreach ($methods as $method) {
            $returnType = $method->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {

                $returnTypeName = $returnType->getName();

                // Проверяем, является ли возвращаемый тип подклассом $parentClassName
                if (is_subclass_of($returnTypeName, $targetClass)) {

                    $result->push($returnTypeName);
                }
            }
        }

        return $result;
    }

    private static function getInstance(): ClientResolver
    {
        if (static::$instance) {
            return static::$instance;
        }

        return static::$instance = new static();
    }
}
