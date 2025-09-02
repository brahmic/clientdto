<?php

namespace Brahmic\ClientDTO;

use Brahmic\ClientDTO\Cache\RequestCacheManager;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ChainInterface;
use Brahmic\ClientDTO\Contracts\ClientDTOInterface;
use Brahmic\ClientDTO\Resolver\ClientResolver;
use Brahmic\ClientDTO\ResourceScanner\ResourceMap;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Traits\BodyFormat;
use Brahmic\ClientDTO\Traits\Headers;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Timeout;
use Closure;
use Illuminate\Support\Collection;

/**
 *
 */
class ClientDTO implements ClientDTOInterface, ChainInterface
{
    use QueryParams, Headers, Timeout, BodyFormat;

    private ?string $baseUrl = null;


    private bool $debug = false;

    private array $cacheClearCallback = [];

    private array $logs = [];

    private bool $cacheEnabled = true;

    private bool $requestCacheEnabled = true;

    private ?int $requestCacheTtl = null;

    public function logs(): array
    {
        return $this->logs;
    }

    public function ifCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     *
     * Need call before baseUrl()
     *
     * @param bool $cache
     * @return $this
     */
    public function cache(bool $cache = true): self
    {
        $this->cacheEnabled = $cache;

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function onClearCache(Closure $closure): static
    {
        $this->cacheClearCallback[] = $closure;
        return $this;
    }


    public function getBaseUrl(?string $uri = ''): ?string
    {
        return $uri ? $this->baseUrl . $uri : $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        if (!$this->baseUrl) {
            ClientResolver::registerClient($this);
        }

        $this->setTimeout(60);

        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getResponseClass(): string
    {
        return ClientResponse::class;
    }

//    public function rebuild(): void
//    {
//        ClientResolver::registerClient($this, true);
//    }

    public function getRequestDeclarations(?Closure $mapperClosure = null): Collection
    {
        $declarations = collect(ClientResolver::getInstance()->determineResourceMap(static::class)->requests);

        if ($mapperClosure) {
            $declarations = $declarations->map(function ($declaration) use ($mapperClosure) {
                $append = $mapperClosure($declaration);
                return is_array($append) ? array_merge($declaration, $append) : $declaration;
            });
        }

        return $declarations;
    }

    public function getRequestClassByKey(string $key): ?string
    {
        return $this->getRequestDeclarations()->search(function ($item) use ($key) {
            return $item['key'] === $key;
        });
    }

    public function createRequestByKey(string $key): AbstractRequest
    {
        if ($class = $this->getRequestClassByKey($key)) {
            return new $class();
        }

        throw new \RuntimeException("Request with `$key` not found`. Try to clear cache");
    }

    public function getResourceMap(): ResourceMap
    {
        return ClientResolver::getInstance()->determineResourceMap(static::class);
    }

    public function clearCache(): void
    {
        array_walk($this->cacheClearCallback, function ($closure) {
            $closure();
        });

        ClientResolver::getInstance()->clearCache(static::class);
        
        // Также очищаем кеш запросов
        $this->clearRequestCache();
    }

    // =====================================================
    // Методы для кеширования HTTP-запросов
    // =====================================================

    /**
     * Включить/выключить кеширование HTTP-запросов
     */
    public function cacheRequests(bool $enabled = true): self
    {
        $this->requestCacheEnabled = $enabled;
        return $this;
    }

    /**
     * Установить TTL по умолчанию для кеширования HTTP-запросов
     */
    public function requestCacheTtl(?int $ttl = null): self
    {
        $this->requestCacheTtl = $ttl;
        return $this;
    }

    /**
     * Проверить, включено ли кеширование запросов
     */
    public function ifRequestCacheEnabled(): bool
    {
        return $this->requestCacheEnabled;
    }

    /**
     * Получить TTL по умолчанию для кеширования HTTP-запросов
     */
    public function getRequestCacheTtl(): ?int
    {
        return $this->requestCacheTtl;
    }

    /**
     * Очистить кеш запросов
     */
    public function clearRequestCache(?string $pattern = null): void
    {
        $cacheManager = new RequestCacheManager();
        $cacheManager->clearCache($pattern);
    }

    /**
     * Очистить кеш запросов по тегам
     */
    public function clearRequestCacheByTags(array $tags): void
    {
        $cacheManager = new RequestCacheManager();
        $cacheManager->clearCacheByTags($tags);
    }

}
