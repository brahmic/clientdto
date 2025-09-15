<?php

namespace Brahmic\ClientDTO;

use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\ChainInterface;
use Brahmic\ClientDTO\Contracts\ClientDTOInterface;
use Brahmic\ClientDTO\Contracts\ResolvedHandlerInterface;
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

    // Новые свойства для кеширования HTTP запросов
    private bool $requestCacheEnabled = false;
    private bool $rawCacheEnabled = false;
    private ?int $requestCacheSize = 1024 * 1024; // 1MB по умолчанию
    private ?int $requestCacheTtl = null; // TTL в секундах (null - без ограничений)
    private bool $postIdempotent = false;

    // Обработчики resolved данных
    private array $resolvedHandlers = [];

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

    // Новые методы для управления кешированием HTTP запросов
    
    /**
     * Включить/выключить кеширование HTTP запросов
     */
    public function requestCache(bool $enabled = true): static
    {
        $this->requestCacheEnabled = $enabled;
        return $this;
    }

    /**
     * Управление уровнем кеширования (RAW данные vs DTO объекты)
     */
    public function requestCacheRaw(bool $enabled = true): static
    {
        $this->rawCacheEnabled = $enabled;
        return $this;
    }

    /**
     * Установить максимальный размер кешируемых объектов
     * @param int|null $bytes Размер в байтах (null или 0 - без ограничений)
     */
    public function requestCacheSize(?int $bytes): static
    {
        $this->requestCacheSize = $bytes;
        return $this;
    }

    /**
     * Установить TTL (время жизни) для кеша запросов
     * @param int|null $seconds TTL в секундах (null - без ограничений)
     */
    public function requestCacheTtl(?int $seconds): static
    {
        $this->requestCacheTtl = $seconds;
        return $this;
    }

    /**
     * Режим идемпотентности POST запросов
     * @param bool $enabled Если true, POST кешируются как идемпотентные
     */
    public function postIdempotent(bool $enabled = true): static
    {
        $this->postIdempotent = $enabled;
        return $this;
    }

    /**
     * Очистить кеш HTTP запросов ClientDTO
     */
    public function clearRequestCache(): void
    {
        $cacheManager = new \Brahmic\ClientDTO\Cache\CacheManager();
        $cacheManager->clearAllClientDtoCache();
    }

    // Геттеры для кеширования запросов
    public function isRequestCacheEnabled(): bool 
    { 
        return $this->requestCacheEnabled; 
    }
    
    public function isRawCacheEnabled(): bool 
    { 
        return $this->rawCacheEnabled; 
    }
    
    public function getRequestCacheSize(): ?int 
    { 
        return $this->requestCacheSize; 
    }
    
    public function getRequestCacheTtl(): ?int 
    { 
        return $this->requestCacheTtl; 
    }
    
    public function isPostIdempotent(): bool 
    { 
        return $this->postIdempotent; 
    }

    /**
     * Добавить обработчик resolved данных
     * 
     * @param callable|ResolvedHandlerInterface $handler Обработчик или функция
     * @param string|null $dtoClass Класс DTO (если null - для всех resolved данных)
     * @return static
     */
    public function addResolvedHandler(
        callable|ResolvedHandlerInterface $handler, 
        ?string $dtoClass = null
    ): static {
        $this->resolvedHandlers[] = [
            'handler' => $handler,
            'dtoClass' => $dtoClass
        ];
        return $this;
    }

    /**
     * Получить зарегистрированные обработчики resolved данных
     * 
     * @return array
     */
    public function getResolvedHandlers(): array 
    {
        return $this->resolvedHandlers;
    }

    public function clearCache(): void
    {
        array_walk($this->cacheClearCallback, function ($closure) {
            $closure();
        });

        ClientResolver::getInstance()->clearCache(static::class);
    }

}
