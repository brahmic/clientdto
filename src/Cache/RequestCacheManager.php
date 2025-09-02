<?php

namespace Brahmic\ClientDTO\Cache;

use Brahmic\ClientDTO\Attributes\Cacheable;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\CacheableRequestInterface;
use Brahmic\ClientDTO\Contracts\GroupedRequest;
use Brahmic\ClientDTO\Contracts\RequestCacheInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RequestCacheManager implements RequestCacheInterface
{
    private CacheKeyBuilder $keyBuilder;
    private CacheDataSerializer $serializer;

    public function __construct()
    {
        $this->keyBuilder = new CacheKeyBuilder();
        $this->serializer = new CacheDataSerializer();
    }

    /**
     * Получить данные из кеша для запроса
     */
    public function getFromCache(AbstractRequest $request): mixed
    {
        try {
            // Проверяем, должен ли запрос кешироваться
            if (!$this->shouldUseCache($request)) {
                return null;
            }

            $cacheKey = $request instanceof GroupedRequest 
                ? $this->keyBuilder->buildGroupedKey($request)
                : $this->keyBuilder->buildKey($request);

            $serializedData = Cache::get($cacheKey);
            
            if ($serializedData === null) {
                return null;
            }

            return $this->serializer->unserialize($serializedData);
            
        } catch (Throwable $e) {
            // При ошибках кеширования не ломаем основную логику
            return null;
        }
    }

    /**
     * Сохранить результат запроса в кеш
     */
    public function storeInCache(AbstractRequest $request, mixed $resolved): void
    {
        try {
            // Проверяем, должен ли запрос кешироваться
            if (!$this->shouldCache($request, $resolved)) {
                return;
            }

            $cacheKey = $request instanceof GroupedRequest 
                ? $this->keyBuilder->buildGroupedKey($request)
                : $this->keyBuilder->buildKey($request);

            $serializedData = $this->serializer->serialize($resolved);
            $ttl = $this->getCacheTtl($request);
            $tags = $this->getCacheTags($request);

            // Сохраняем с тегами если поддерживается, иначе без тегов
            if (!empty($tags) && method_exists(Cache::getStore(), 'tags')) {
                Cache::tags($tags)->put($cacheKey, $serializedData, $ttl ? now()->addSeconds($ttl) : null);
            } else {
                if ($ttl !== null) {
                    Cache::put($cacheKey, $serializedData, now()->addSeconds($ttl));
                } else {
                    Cache::put($cacheKey, $serializedData);
                }
            }
            
        } catch (Throwable $e) {
            // При ошибках кеширования не ломаем основную логику
        }
    }

    /**
     * Очистить кеш по паттерну
     */
    public function clearCache(?string $pattern = null): void
    {
        try {
            if ($pattern === null) {
                // Очищаем все ключи clientdto (requests + grouped)
                $pattern = 'clientdto.*';
            }

            // Если Cache store поддерживает flush по паттерну
            if (method_exists(Cache::getStore(), 'deleteByPattern')) {
                Cache::getStore()->deleteByPattern($pattern);
            } else {
                // Fallback - нужна более сложная логика для каждого драйвера
                Cache::flush(); // Осторожно - очищает весь кеш!
            }
        } catch (Throwable $e) {
            // Логирование ошибки
        }
    }

    /**
     * Очистить кеш по тегам
     */
    public function clearCacheByTags(array $tags): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags($tags)->flush();
            }
        } catch (Throwable $e) {
            // Логирование ошибки
        }
    }

    /**
     * Проверить, должен ли запрос использовать кеш для чтения
     */
    private function shouldUseCache(AbstractRequest $request): bool
    {
        // Абсолютный приоритет: если запрос помечен для пропуска кеша
        if ($request->shouldSkipCache()) {
            return false;
        }

        // Принудительное обновление кеша: skipCache(false)
        // НЕ читаем из кеша (принудительно делаем HTTP-запрос)
        if ($request->isForceCacheEnabled()) {
            return false;
        }

        // Стандартная логика: проверяем все уровни
        if (!$request->getClientDTO()->ifRequestCacheEnabled()) {
            return false;
        }

        $cacheableAttribute = $this->getCacheableAttribute($request);
        if ($cacheableAttribute && !$cacheableAttribute->enabled) {
            return false;
        }

        return true;
    }

    /**
     * Проверить, должен ли результат кешироваться
     */
    private function shouldCache(AbstractRequest $request, mixed $resolved): bool
    {
        // Абсолютный приоритет: если запрос помечен для пропуска кеша
        if ($request->shouldSkipCache()) {
            return false;
        }

        // Проверяем, можно ли кешировать данные
        if (!$this->serializer->canCache($resolved)) {
            return false;
        }

        // Принудительное обновление кеша: skipCache(false)
        // Кешируем результат (несмотря на глобальные настройки)
        if ($request->isForceCacheEnabled()) {
            $cacheableAttribute = $this->getCacheableAttribute($request);
            if ($cacheableAttribute && !$cacheableAttribute->enabled) {
                return false;
            }
            // Проверяем shouldCache запроса (например, для FileResponse)
            return $request->shouldCache($resolved);
        }

        // Стандартная логика: проверяем все уровни
        if (!$this->shouldUseCache($request)) {
            return false;
        }

        // Вызываем метод shouldCache запроса
        if ($request instanceof CacheableRequestInterface) {
            return $request->shouldCache($resolved);
        }

        // По умолчанию кешируем все кроме FileResponse
        return $request->shouldCache($resolved);
    }

    /**
     * Получить TTL для кеша
     */
    private function getCacheTtl(AbstractRequest $request): ?int
    {
        // Сначала из CacheableRequestInterface
        if ($request instanceof CacheableRequestInterface) {
            $ttl = $request->getCacheTtl();
            if ($ttl !== null) {
                return $ttl;
            }
        }

        // Затем из атрибута Cacheable
        $cacheableAttribute = $this->getCacheableAttribute($request);
        if ($cacheableAttribute && $cacheableAttribute->ttl !== null) {
            return $cacheableAttribute->ttl;
        }

        // Затем из настроек ClientDTO
        $clientTtl = $request->getClientDTO()->getRequestCacheTtl();
        if ($clientTtl !== null) {
            return $clientTtl;
        }

        // По умолчанию - из конфигурации
        return CacheConfig::getDefaultTtl();
    }

    /**
     * Получить теги для кеша
     */
    private function getCacheTags(AbstractRequest $request): array
    {
        $tags = [];

        // Из CacheableRequestInterface
        if ($request instanceof CacheableRequestInterface) {
            $tags = array_merge($tags, $request->getCacheTags());
        }

        // Из атрибута Cacheable
        $cacheableAttribute = $this->getCacheableAttribute($request);
        if ($cacheableAttribute) {
            $tags = array_merge($tags, $cacheableAttribute->tags);
        }

        return array_unique($tags);
    }

    /**
     * Получить атрибут Cacheable из класса запроса
     */
    private function getCacheableAttribute(AbstractRequest $request): ?Cacheable
    {
        $reflection = new \ReflectionClass($request);
        $attributes = $reflection->getAttributes(Cacheable::class);
        
        return $attributes ? $attributes[0]->newInstance() : null;
    }
}
