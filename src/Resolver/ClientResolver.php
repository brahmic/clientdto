<?php

namespace Brahmic\ClientDTO\Resolver;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\ResourceScanner\ResourceMap;
use Illuminate\Support\Collection;

class ClientResolver
{
    private static ?ClientResolver $instance = null;
    private ClientRegistry $registry;

    private function __construct()
    {
        $this->registry = new ClientRegistry();
    }

    public function clearCache(string $clientClass): void
    {
        $this->registry->clearCache($clientClass);
    }

    public function determineResourceMap(string $class): ?ResourceMap
    {
        return $this->registry->determineResourceMap($class);
    }

    public static function registerClient(ClientDTO $clientDTO, bool $force = false): void
    {
        self::getInstance()->registry->register($clientDTO, $force);
    }

    public static function getFullyInstantiatedChainOfRequest(AbstractRequest $request): Collection
    {
        $resourceMap = self::getInstance()->determineResourceMap($request::class);

        return $resourceMap
            ->getRequestResources($request::class)
            ->prepend($resourceMap->getRootClass())
            ->map(fn($chain) => app()->make($chain))
            ->push($request);
    }

    /**
     * Resolve resource/request ClientDTO.
     *
     * @param string $class Class of ClientDTO, resource or request
     * @return ClientDTO
     * @throws \Exception
     */
    public static function resolveClientDto(string $class): ClientDTO
    {
        return self::getInstance()->registry->resolveClientDto($class);
    }

    public static function getInstance(): ClientResolver
    {
        if (static::$instance) {
            return static::$instance;
        }

        return static::$instance = new static();
    }
}
