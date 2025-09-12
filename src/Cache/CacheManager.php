<?php

namespace Brahmic\ClientDTO\Cache;

use Brahmic\ClientDTO\Attributes\Cachable;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Response\FileResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Менеджер кеширования HTTP запросов ClientDTO
 * 
 * Обеспечивает:
 * - Логику приоритетов настроек кеширования
 * - RAW и DTO кеширование
 * - Graceful degradation при ошибках
 * - Стабильную генерацию ключей кеша
 * - Размерные ограничения
 */
class CacheManager
{
    /**
     * Проверить, следует ли использовать кеш для чтения
     */
    public function shouldUseCache(AbstractRequest $request): bool
    {
        // 1. Принудительные методы запроса (САМЫЙ ВЫСОКИЙ приоритет)
        if ($request->shouldSkipCache()) {
            return false;
        }
        
        if ($request->shouldForceCache()) {
            return true; // Игнорируем все остальные проверки
        }
        
        // 2. Атрибут класса запроса  
        $cachable = $this->getCachableAttribute($request);
        if ($cachable && !$cachable->enabled) {
            return false;
        }
        if ($cachable && $cachable->enabled) {
            return $request->getClientDTO()->isRequestCacheEnabled();
        }
        
        // 3. Специальная логика для POST и идемпотентности
        // Если идемпотентность ВЫКЛЮЧЕНА, то POST не кешируем по умолчанию
        if ($request->getMethod() === 'post' && !$request->getClientDTO()->isPostIdempotent()) {
            return false; // POST без идемпотентности не кешируем
        }
        
        // 4. Глобальная настройка клиента (САМЫЙ НИЗКИЙ приоритет) 
        return $request->getClientDTO()->isRequestCacheEnabled();
    }

    /**
     * Проверить, следует ли сохранять результат в кеш
     */
    public function shouldStoreCache(AbstractRequest $request): bool
    {
        // skipCache() влияет только на ЧТЕНИЕ, НЕ на запись!
        // forceCache() включает и чтение, и запись
        
        if ($request->shouldForceCache()) {
            return true;
        }
        
        // 1. Атрибут класса запроса
        $cachable = $this->getCachableAttribute($request);
        if ($cachable && !$cachable->enabled) {
            return false;
        }
        if ($cachable && $cachable->enabled) {
            return $request->getClientDTO()->isRequestCacheEnabled();
        }
        
        // 2. Специальная логика для POST и идемпотентности
        // Если идемпотентность ВЫКЛЮЧЕНА, то POST не кешируем по умолчанию
        if ($request->getMethod() === 'post' && !$request->getClientDTO()->isPostIdempotent()) {
            return false; // POST без идемпотентности не кешируем
        }
        
        // 3. Глобальная настройка клиента
        return $request->getClientDTO()->isRequestCacheEnabled();
    }

    /**
     * Получить данные из кеша
     */
    public function get(AbstractRequest $request): ?CachedResponse
    {
        try {
            $cacheKey = $this->buildKey($request);
            $tags = ['clientdto'];
            $cachedData = Cache::tags($tags)->get($cacheKey);
            
            if ($cachedData === null) {
                return null;
            }
            
            return $cachedData;
            
        } catch (Throwable $e) {
            // Graceful degradation - возвращаем null при ошибках
            return null;
        }
    }

    /**
     * Сохранить результат в кеш
     */
    public function store(AbstractRequest $request, mixed $resolved, ?string $rawData): void
    {
        try {
            $cacheKey = $this->buildKey($request);
            $isRawCache = $request->getClientDTO()->isRawCacheEnabled();
            
            // Создаем запись в кеше
            if ($isRawCache) {
                // При RAW кешировании сохраняем только RAW данные
                $cachedResponse = CachedResponse::raw(null, $rawData);
            } else {
                // При DTO кешировании сохраняем готовый DTO + raw данные для saveAs()
                $cachedResponse = CachedResponse::dto($resolved, $rawData);
            }
            
            // Получаем TTL
            $ttl = $this->getCacheTtl($request);
            $tags = ['clientdto'];
            
            // Сохраняем в кеш с тегами
            if ($ttl !== null) {
                Cache::tags($tags)->put($cacheKey, $cachedResponse, now()->addSeconds($ttl));
            } else {
                Cache::tags($tags)->put($cacheKey, $cachedResponse);
            }
            
        } catch (Throwable $e) {
            // Graceful degradation - не прерываем выполнение при ошибках кеша
        }
    }

    /**
     * Проверить превышает ли объект размерные ограничения
     */
    public function isObjectTooLarge(mixed $data, AbstractRequest $request): bool
    {
        $maxSize = $request->getClientDTO()->getRequestCacheSize();
        
        if ($maxSize === null || $maxSize === 0) {
            return false; // Без ограничений
        }
        
        try {
            $size = strlen(serialize($data));
            return $size > $maxSize;
        } catch (Throwable $e) {
            // Если не можем сериализовать - считаем слишком большим
            return true;
        }
    }

    /**
     * Очистить весь кеш ClientDTO
     */
    public function clearAllClientDtoCache(): void
    {
        try {
            Cache::tags(['clientdto'])->flush();
        } catch (Throwable $e) {
            // Graceful degradation
        }
    }

    /**
     * Построить ключ кеша для запроса
     */
    public function buildKey(AbstractRequest $request): string
    {
        $keyData = [
            'class' => $request::class,
            'method' => $request->getMethod(),
            'baseUrl' => $request->getBaseUrl(),
            'params' => $this->normalizeParams($request->original()),
            // Добавляем тип кеширования для разделения RAW и DTO кешей
            'cacheType' => $request->getClientDTO()->isRawCacheEnabled() ? 'raw' : 'dto'
        ];
        
        return 'clientdto:request:' . hash('sha256', serialize($keyData));
    }

    /**
     * Получить атрибут Cachable из класса запроса
     */
    private function getCachableAttribute(AbstractRequest $request): ?\Brahmic\ClientDTO\Attributes\Cacheable
    {
        $reflection = new \ReflectionClass($request);
        $attributes = $reflection->getAttributes(\Brahmic\ClientDTO\Attributes\Cacheable::class);
        
        return $attributes ? $attributes[0]->newInstance() : null;
    }

    /**
     * Получить TTL для кеша с учетом приоритетов
     */
    private function getCacheTtl(AbstractRequest $request): ?int
    {
        // 1. Из атрибута Cachable (высший приоритет)
        $cachable = $this->getCachableAttribute($request);
        if ($cachable && $cachable->ttl !== null) {
            return $cachable->ttl;
        }

        // 2. Глобальная настройка клиента
        $clientTtl = $request->getClientDTO()->getRequestCacheTtl();
        if ($clientTtl !== null) {
            return $clientTtl;
        }
        
        // 3. По умолчанию - без ограничений
        return null;
    }


    /**
     * Нормализовать параметры для стабильных ключей кеша
     */
    private function normalizeParams(array $params): array
    {
        ksort($params); // Сортировка для стабильности
        
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->normalizeParams($value);
            } elseif (is_object($value)) {
                $params[$key] = $this->normalizeObject($value);
            }
        }
        
        return $params;
    }

    /**
     * Нормализовать объект для стабильного ключа кеша
     */
    private function normalizeObject(object $obj): string
    {
        // Enum - стабильно
        if ($obj instanceof \UnitEnum) {
            return method_exists($obj, 'value') ? (string)$obj->value : $obj->name;
        }
        
        // DateTime - стабильно  
        if ($obj instanceof \DateTimeInterface) {
            return $obj->format('Y-m-d H:i:s');
        }
        
        // __toString
        if (method_exists($obj, '__toString')) {
            return (string)$obj;
        }
        
        // Публичные свойства (параметры запросов)
        $reflection = new \ReflectionClass($obj);
        $data = ['class' => $reflection->getName()];
        
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($obj);
            $data[$property->getName()] = is_array($value) ? $this->normalizeParams($value) : $value;
        }
        
        return hash('sha256', serialize($data));
    }
}
