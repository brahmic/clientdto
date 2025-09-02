# Кеширование HTTP-запросов в ClientDTO

## Обзор

ClientDTO теперь поддерживает автоматическое кеширование успешных HTTP-запросов. Кеширование работает на уровне финального результата после всей обработки (включая `postProcess`, валидацию, трансформацию).

## Быстрый старт

```php
// Включаем кеширование запросов в клиенте
class MyClient extends ClientDTO
{
    public function __construct()
    {
        $this
            ->setBaseUrl('https://api.example.com/')
            ->cache(true)           // Кеш метаданных (как было)
            ->cacheRequests(true);  // Кеш HTTP-запросов (НОВОЕ!)
    }
}

// Используем как обычно - кеширование автоматическое
$users = $client->users()->list()->set(page: 1)->send()->resolved();
```

## Способы управления кешированием

### 1. Глобальное управление (на уровне клиента)

```php  
$client->cacheRequests(true);   // Включить кеширование запросов
$client->cacheRequests(false);  // Выключить кеширование запросов
$client->cache(false);          // Выключить весь кеш (включая запросы)
```

### 2. Декларативное управление (атрибут на классе)

```php
#[Cacheable(ttl: 3600, tags: ['users'], enabled: true)]
class UserListRequest extends GetRequest
{
    // TTL: 3600 секунд (1 час)
    // Теги: ['users'] для групповой инвалидации  
    // Включено: true
}
```

### 3. Программное управление (интерфейс)

```php
class UserShowRequest extends GetRequest implements CacheableRequestInterface
{
    public function getCacheTtl(): ?int
    {
        return $this->include_details ? 300 : 3600; // 5 мин или 1 час
    }

    public function getCacheTags(): array 
    {
        return ['users', "user:{$this->id}"];
    }

    public function shouldCache(mixed $resolved): bool
    {
        return $resolved instanceof UserDto && $resolved->isActive();
    }
}
```

## Алгоритм построения ключей кеша

Ключи кеша строятся автоматически на основе:

- **Класс запроса** - уникальность типа запроса
- **HTTP метод** - GET vs POST
- **URL** - с подставленными параметрами пути (`{id}`, `{uuid}`)
- **Query параметры** - отсортированные, с учетом `#[HideFromQueryStr]`
- **Body параметры** - отсортированные, с учетом `#[HideFromBody]`

**Пример ключей:**
```
clientdto.request.a1b2c3d4e5f6...  // GET users?page=1&search=john
clientdto.request.x7y8z9w0v1u2...  // GET users?page=2&search=john  
clientdto.request.m3n4o5p6q7r8...  // POST users (body: name=John)
```

## Особенности работы

### Что кешируется
- ✅ Успешные HTTP-запросы (status 200)
- ✅ После полной обработки (включая `postProcess`)
- ✅ DTO объекты, массивы, примитивы
- ❌ **НЕ кешируется**: `FileResponse` объекты

### Точки интеграции
- **Проверка кеша**: перед выполнением запроса
- **Сохранение в кеш**: после полной обработки ответа
- **Групповые запросы**: кешируется вся группа как единое целое
- **Цепочки запросов**: каждый запрос + финальный результат

### Приоритеты настроек
1. **Глобальный флаг** `cacheRequests()` - может отключить всё
2. **Атрибут** `#[Cacheable(enabled: false)]` - отключает конкретный запрос
3. **Метод** `shouldCache()` - финальная проверка результата

## Управление кешем

### Очистка кеша

```php
// Очистить весь кеш запросов
$client->clearRequestCache();

// Очистить по тегам
$client->clearRequestCacheByTags(['users']);
$client->clearRequestCacheByTags(['user:123']);

// При вызове общего clearCache() также очищается кеш запросов
$client->clearCache();
```

### Инвалидация по тегам

```php
// В запросе указываем теги
#[Cacheable(tags: ['users', 'user:123'])]

// В контроллере очищаем по тегам
$client->clearRequestCacheByTags(['user:123']); // Конкретный пользователь
$client->clearRequestCacheByTags(['users']);    // Все пользователи
```

## Совместимость

### С существующим кодом
- ✅ **100% обратная совместимость** - ничего не ломается
- ✅ **Опциональность** - кеширование по умолчанию включено, но можно отключить
- ✅ **Существующие методы** - `queryParams()`, `bodyParams()`, `getUrl()` используются без изменений

### С различными типами запросов  
- ✅ **GET запросы** - query параметры в ключе
- ✅ **POST запросы** - query + body параметры  
- ✅ **GroupedRequest** - кеш всей группы
- ✅ **PaginableRequest** - page/rows в ключе кеша
- ✅ **Цепочки** - параметры цепи учитываются в ключе

## Логирование

Кеширование добавляет записи в лог запроса:

```
Execute `UserListRequest` request
Cache miss for request UserListRequest  
Preparing...
JSON received
DTO `UserListDto` resolved!
Cached result for request UserListRequest
Successful
Finish
```

```
Execute `UserListRequest` request
Cache hit for request UserListRequest
Successful (cached)
Finish
```

## Производительность

- **Минимальный оверхед** - проверка кеша занимает ~1ms
- **Компактные ключи** - MD5 хеш от параметров  
- **Эффективная сериализация** - стандартный PHP `serialize()`
- **Laravel Cache** - используется настроенный драйвер (Redis, Memcached, File)

## Диагностика

### Проверка работы кеширования

```php
// Проверим логи для диагностики
$response = $client->users()->list()->send();
$logs = $response->getLogs(); // Ищем "Cache hit" или "Cache miss"

// Проверим статус кеширования
$isEnabled = $client->ifRequestCacheEnabled(); // true/false
```

### Отладка ключей кеша

```php
// Для отладки можно временно логировать ключи
$keyBuilder = new \Brahmic\ClientDTO\Cache\CacheKeyBuilder();
$key = $keyBuilder->buildKey($request);
\Log::info("Cache key: " . $key);
```

## Ограничения

- **FileResponse** не кешируются (по дизайну)
- **Неудачные запросы** не кешируются  
- **Cache tags** работают не во всех драйверах Laravel Cache
- **Размер кеша** ограничен настройками Laravel Cache

## FAQ

**Q: Влияет ли кеширование на существующий код?**  
A: Нет, 100% обратная совместимость. Кеширование прозрачно.

**Q: Можно ли кешировать только определенные запросы?**  
A: Да, используйте `#[Cacheable(enabled: false)]` или `shouldCache()`.

**Q: Как кешируются запросы с разными параметрами?**  
A: Каждая уникальная комбинация параметров = свой ключ кеша.

**Q: Что если изменилась структура DTO?**  
A: При ошибках десериализации кеш игнорируется, выполняется обычный запрос.

**Q: Как долго хранится кеш?**  
A: По умолчанию - настройка Laravel Cache. Можно переопределить через `getCacheTtl()`.
