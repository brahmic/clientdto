<?php

namespace Brahmic\ClientDTO\Cache;

use Brahmic\ClientDTO\Contracts\AbstractRequest;

class CacheKeyBuilder
{
    /**
     * Построить ключ кеша для запроса
     * Laravel way подход с Collections
     */
    public function buildKey(AbstractRequest $request): string
    {
        $keyComponents = collect([
            'request_class' => get_class($request),
            'method' => $request->getMethod(),  // 'get' | 'post'
            'url' => $request->getUrl(),       // URL с подставленными {uuid}, {id} 
            'query_params' => collect($request->queryParams())->sortKeys(),
            'body_params' => collect($request->bodyParams())->sortKeys(),
        ])
        ->reject(fn($value, $key) => $key === 'body_params' && $value->isEmpty())
        ->sortKeys()
        ->toJson(JSON_UNESCAPED_UNICODE);
        
        return CacheConfig::getRequestPrefix() . md5($keyComponents);
    }

    /**
     * Построить ключ для групповых запросов
     */
    public function buildGroupedKey(AbstractRequest $request): string
    {
        $keyComponents = collect([
            'request_class' => get_class($request),
            'method' => 'grouped',
            'properties' => collect($request->getOwnPublicProperties())->sortKeys(),
        ])
        ->sortKeys()
        ->toJson(JSON_UNESCAPED_UNICODE);
        
        return CacheConfig::getGroupedPrefix() . md5($keyComponents);
    }
}
