<?php

namespace Brahmic\ClientDTO\Contracts;

interface RequestCacheInterface 
{
    /**
     * Получить данные из кеша для запроса
     * @param AbstractRequest $request
     * @return mixed|null Данные из кеша или null если не найдено
     */
    public function getFromCache(AbstractRequest $request): mixed;

    /**
     * Сохранить результат запроса в кеш
     * @param AbstractRequest $request
     * @param mixed $resolved Результат для кеширования
     */
    public function storeInCache(AbstractRequest $request, mixed $resolved): void;

    /**
     * Очистить кеш по паттерну ключа
     * @param string|null $pattern Паттерн ключа, null - очистить всё
     */
    public function clearCache(?string $pattern = null): void;

    /**
     * Очистить кеш по тегам
     * @param array $tags Массив тегов
     */
    public function clearCacheByTags(array $tags): void;
}
