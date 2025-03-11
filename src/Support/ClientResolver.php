<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\AbstractResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

        $data = $resolver->createClientResourcesMap($clientDTOClass, $force);

        $resolver->clients[$resolver->getKey($clientDTOClass)] = $data;

        return $data;
    }

    public static function getChain(AbstractRequest $request): Collection
    {
        return self::getInstance()->getClassChain($request::class)->map(function (string $chain) use ($request) {
            return app()->make($chain);
        });
    }


    /**
     * Resolve ClientDTO.
     *
     * @param string $class
     * @return ClientDTO
     * @throws \Exception
     */
    public static function resolve(string $class): ClientDTO
    {
        $resolver = self::getInstance();

        return app($resolver->getClassChain($class)->get(0));
    }

    private function getClassChain(string $class): Collection
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

        return collect($data);
    }

    private function extractClassName(string $resourceClass): string
    {
        //dump($this->extractRegistered($resourceClass));
        //dd($resourceClass);
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

        if (Cache::has($cacheKey) && !$force) {
            return Cache::get($cacheKey);
        }

        $result = collect([
            $this->getKey($clientClass) => $clientClass,
            ...$this->buildCallMap($clientClass, $force),
        ]);
//dd($result);
        Cache::put($cacheKey, $result, Carbon::now()->addMonth());

        return $result;
    }


    function buildCallMap(string $className, bool $force = false, array &$callMap = [], array &$currentPath = []): array
    {
        static $visitedAssoc = [];

        if ($force) {
            $visitedAssoc = [];
        }

        if (isset($visitedAssoc[$className])) {
            return [];
        }

        $visitedAssoc[$className] = true;
//
//        if (!is_subclass_of($className, AbstractRequest::class)) {
//
//        }

        $currentPath[] = $className;

        $reflection = new ReflectionClass($className);

        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {
                $returnTypeName = $returnType->getName();

                if (is_subclass_of($returnTypeName, AbstractResource::class) ||
                    is_subclass_of($returnTypeName, AbstractRequest::class)) {
                    $this->buildCallMap($returnTypeName, false, $callMap, $currentPath);
                }
            }
        }

        if (is_subclass_of($className, AbstractRequest::class)) {
            $callMap[$className] = array_slice($currentPath, 0, -1);
        }

        array_pop($currentPath);

        return $callMap;
    }

    private static function getInstance(): ClientResolver
    {
        if (static::$instance) {
            return static::$instance;
        }

        return static::$instance = new static();
    }
}
