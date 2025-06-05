<?php

namespace Brahmic\ClientDTO\Resolver;


use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\ResourceScanner\ResourceMap;
use Brahmic\ClientDTO\ResourceScanner\Scanner;

class ClientRegistry
{
    private array $clients = [];
    private ClientCache $cache;

    public function __construct()
    {
        $this->cache = new ClientCache();
    }

    public function clearCache(string $clientClass): void
    {
        $this->cache->clear($cacheKey = 'clientdto.resourceMap.' . self::getKey($clientClass));
    }

    public function register(ClientDTO $clientDTO, bool $force = false): void
    {
        $this->build($clientDTO, $force);
    }

    public function build(ClientDTO $clientDTO, bool $force = false): void
    {
        $this->clients[self::getKey($clientDTO::class)] = [
            'resourceMap' => $this->getRequestMap($clientDTO, $force),
            'instance' => $clientDTO,
        ];
    }

    public function has(string $clientClass): bool
    {
        return isset($this->clients[self::getKey($clientClass)]);
    }

    public function determineResourceMap(string $someClass): ?ResourceMap
    {
        return $this->clients[self::getKey($someClass)]['resourceMap'] ?? null;
    }

//    public function getByKey(string $key): ?ResourceMap
//    {
//        return $this->clients[$key]['resourceMap'] ?? null;
//    }
//
//    public function find($clientClass): ?ClientDTO
//    {
//        return array_find($this->clients, function ($value) use ($clientClass) {
//            return $value['class'] === $clientClass;
//        });
//    }

    public function resolveClientDto(string $class): ClientDTO
    {
        if ($resourceMap = $this->determineResourceMap($class)) {
            return app($resourceMap->getRootClass());
        }

        throw new \Exception("Can't resolve client of `$class`");
    }

    private function getRequestMap(ClientDTO $clientDTO, bool $force = false): ResourceMap
    {
        $clientKey = self::getKey($clientDTO::class);

        $cacheKey = 'clientdto.resourceMap.' . $clientKey;

        if ($clientDTO->ifCacheEnabled()) {
            if ($this->cache->has($cacheKey) && !$force) {
                return $this->cache->get($cacheKey);
            }
        }
        $resourceMap = new Scanner()->scanClient($clientDTO::class);
//dd('end', $resourceMap);

        $this->cache->put($cacheKey, $resourceMap);

        return $resourceMap;
    }


    public static function getKey(string|object $classOrObject): string
    {
        $class = is_string($classOrObject) ? $classOrObject : $classOrObject::class;

        $parts = explode('\\', $class);
        return implode('\\', array_slice($parts, 0, 2));
    }

}
