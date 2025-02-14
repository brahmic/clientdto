<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\AbstractResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;

class ClientResolver
{
    private static ?ClientResolver $instance = null;

    private array $clients = [];


    public static function registerClient(ClientDTO|string $clientDTO, bool $force = false): array
    {
        $resolver = self::getInstance();

        $clientDTOClass = is_object($clientDTO) ? $clientDTO::class : $clientDTO;

        $data = [
            'clientClass' => $clientDTOClass,
            'resourceMap' => $resolver->createClientResourcesMap($clientDTOClass, $force),
        ];

        $resolver->clients[$resolver->getKey($clientDTOClass)] = $data;

        return $data;
    }

    public static function resolve(string $resourceClass): ClientDTO
    {
        $resolver = self::getInstance();

        $clientClass = $resolver->getResourceMap($resourceClass)->get($resourceClass);

        if (!$clientClass) {

            //try again, maybe it was renamed
            $resolver->registerClient($resolver->extractClassName($resourceClass), force: true);

            if (!$clientClass = $resolver->getResourceMap($resourceClass)->get($resourceClass)) {

                // need to check $resourceClass namespace
                throw new \Exception("Can't resolve Resource or request with name {$resourceClass}.");
            }

        }

        return app($clientClass);
    }

    private function extractClassName(string $resourceClass): string
    {
        return $this->extractRegistered($resourceClass)['clientClass'];
    }

    private function getResourceMap(string $resourceClass): Collection
    {
        return $this->extractRegistered($resourceClass)['resourceMap'];
    }

    private function extractRegistered(string $class): array
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

        if (Cache::has($cacheKey) && !$force) {
            return Cache::get($cacheKey);
        }

        $collectedClientResources = $this->collectClientResources($clientClass);

        $resources = $collectedClientResources->mapWithKeys(function ($value, $key) {
            return [$key => $value['client']];
        });

        $requests = $collectedClientResources
            ->map(function ($resource, $key) {
                return $resource['requests']->mapWithKeys(function ($value) use ($key, $resource) {
                    return [$value => $resource['client']];
                });
            })
            ->flatMap(fn($items) => $items)
            ->toArray();

        $collected = $resources->merge($requests);

        Cache::put($cacheKey, $collected, Carbon::now()->addMonth());

        return $resources->merge($requests);
    }

    private function collectClientResources(string $clientClass): Collection
    {
        return $this->collectResources($clientClass, AbstractResource::class)
            ->mapWithKeys(function (string $resourceClass) use ($clientClass) {
                return [
                    $resourceClass => collect([
                        'client' => $clientClass,
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
            if ($returnType) {
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