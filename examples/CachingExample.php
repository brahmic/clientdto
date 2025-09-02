<?php

namespace Brahmic\ClientDTO\Examples;

use Brahmic\ClientDTO\Attributes\Cacheable;
use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\AbstractRequest;
use Brahmic\ClientDTO\Contracts\CacheableRequestInterface;
use Brahmic\ClientDTO\Requests\GetRequest;

/**
 * ПРИМЕР ИСПОЛЬЗОВАНИЯ КЕШИРОВАНИЯ HTTP-ЗАПРОСОВ
 * 
 * Демонстрирует все возможности кеширования:
 * - Глобальное управление кешированием 
 * - Декларативное кеширование через атрибут
 * - Программное управление через интерфейс
 * - Управление кешированием на уровне экземпляра (skipCache)
 * - Различные TTL и теги для инвалидации
 */

// ============================================
// 1. НАСТРОЙКА КЛИЕНТА С КЕШИРОВАНИЕМ
// ============================================

class ExampleClient extends ClientDTO
{
    public function __construct()
    {
        $this
            ->setBaseUrl('https://api.example.com/')
            ->cache(true)              // Кеш метаданных (ResourceMap)
            ->cacheRequests(true)      // Кеш HTTP-запросов
            ->requestCacheTtl(3600)           // 🆕 TTL по умолчанию: 1 час
            ->setTimeout(30);
    }

    public function users(): UserResource
    {
        return new UserResource();
    }

    // Обработка ответов (как было)
    public static function handle(array $data): mixed
    {
        return $data;
    }

    // Валидация ответов (как было) 
    public function validation(mixed $data, AbstractRequest $request, $response): mixed
    {
        if (is_array($data) && !empty($data)) {
            return true;
        }
        throw new \Exception('Invalid response data');
    }
}

// ============================================
// 2. РЕСУРС (БЕЗ ИЗМЕНЕНИЙ)
// ============================================

class UserResource extends \Brahmic\ClientDTO\Contracts\AbstractResource
{
    public function list(): UserListRequest
    {
        return new UserListRequest();
    }

    public function show(): UserShowRequest  
    {
        return new UserShowRequest();
    }
}

// ============================================
// 3. ЗАПРОСЫ С РАЗЛИЧНЫМ КЕШИРОВАНИЕМ
// ============================================

/**
 * Пример 1: Декларативное кеширование через атрибут
 * - TTL: 1800 секунд (30 минут)
 * - Теги: ['users', 'list'] для групповой инвалидации
 */
#[Cacheable(ttl: 1800, tags: ['users', 'list'], enabled: true)]
class UserListRequest extends GetRequest
{
    public const string URI = 'users';
    protected ?string $dto = UserListDto::class;

    public ?int $page = 1;
    public ?int $per_page = 10; 
    public ?string $search = null;
    public ?string $status = null;

    public function set(?int $page = null, ?int $per_page = null, ?string $search = null, ?string $status = null): static
    {
        return $this->assignSetValues();
    }
}

/**
 * Пример 2: Программное управление через интерфейс  
 * - Динамический TTL в зависимости от параметров
 * - Динамические теги с ID пользователя
 * - Условное кеширование
 */
class UserShowRequest extends GetRequest implements CacheableRequestInterface
{
    public const string URI = 'users/{id}';
    protected ?string $dto = UserDto::class;

    public string $id;
    public ?bool $include_posts = false;

    public function set(string $id, ?bool $include_posts = false): static
    {
        return $this->assignSetValues();
    }

    // Программное управление кешированием
    public function getCacheTtl(): ?int
    {
        // Запросы с постами кешируем на 5 минут, без постов - на час
        return $this->include_posts ? 300 : 3600;
    }

    public function getCacheTags(): array
    {
        return ['users', "user:{$this->id}"];
    }

    public function shouldCache(mixed $resolved): bool
    {
        // Кешируем только если пользователь активен
        return $resolved instanceof UserDto && $resolved->status === 'active';
    }
}

// ============================================
// 4. DTO КЛАССЫ (БЕЗ ИЗМЕНЕНИЙ)
// ============================================

class UserListDto extends \Brahmic\ClientDTO\Support\Data
{
    public array $data;
    public int $total;
    public int $current_page;
    public int $per_page;
}

class UserDto extends \Brahmic\ClientDTO\Support\Data
{
    public int $id;
    public string $name;
    public string $email;
    public string $status;
    public ?array $posts = null;
}

// ============================================
// 5. ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ============================================

class CachingExamples
{
    private ExampleClient $client;

    public function __construct()
    {
        $this->client = new ExampleClient();
    }

    /**
     * Пример 1: Обычное использование с автоматическим кешированием
     */
    public function basicCaching()
    {
        // Первый вызов - выполняется HTTP-запрос, результат кешируется
        $users1 = $this->client->users()->list()->set(page: 1, per_page: 20)->send()->resolved();
        
        // Второй вызов с теми же параметрами - возвращается из кеша
        $users2 = $this->client->users()->list()->set(page: 1, per_page: 20)->send()->resolved();
        
        // Третий вызов с другими параметрами - новый HTTP-запрос
        $users3 = $this->client->users()->list()->set(page: 2, per_page: 20)->send()->resolved();
    }

    /**
     * Пример 2: Управление кешированием
     */
    public function cacheManagement()
    {
        // Временно отключить кеширование
        $this->client->cacheRequests(false);
        $users = $this->client->users()->list()->send()->resolved(); // Всегда HTTP-запрос
        
        // Включить обратно с новым TTL
        $this->client->cacheRequests(true)->requestCacheTtl(1800); // 30 минут
        
        // Изменить TTL для существующего клиента
        $this->client->requestCacheTtl(7200); // 2 часа
        
        // Очистить весь кеш запросов
        $this->client->clearRequestCache();
        
        // Очистить кеш по тегам
        $this->client->clearRequestCacheByTags(['users']);
        
        // Очистить кеш конкретного пользователя
        $this->client->clearRequestCacheByTags(['user:123']);
    }

    /**
     * Пример 3: Различные запросы с разным кешированием
     */
    public function differentCachingStrategies()
    {
        // UserListRequest: кеш на 30 минут через атрибут
        $usersList = $this->client->users()->list()->set(page: 1)->send()->resolved();
        
        // UserShowRequest: кеш на 1 час (без постов) через интерфейс  
        $user1 = $this->client->users()->show()->set(id: '123')->send()->resolved();
        
        // UserShowRequest: кеш на 5 минут (с постами) через интерфейс
        $user2 = $this->client->users()->show()->set(id: '123', include_posts: true)->send()->resolved();
    }

    /**
     * Пример 4: Управление кешированием на уровне экземпляра (НОВОЕ!)
     */
    public function instanceLevelCaching()
    {
        // 1. Пропустить кеш (не читать, не писать) - всегда делать HTTP-запрос
        $freshUser = $this->client->users()->show()
            ->set(id: '123')
            ->skipCache()          // Пропустить кеш
            ->send()->resolved();
        
        // 2. Принудительно обновить кеш (даже если глобально выключено)
        $this->client->cacheRequests(false); // Глобально выключили
        
        $updatedUser = $this->client->users()->show()
            ->set(id: '123')
            ->skipCache(false)     // Принудительно обновить кеш
            ->send()->resolved();  // HTTP-запрос + обновление кеша
        
        // 3. Цепочка с пропуском кеша - как в вашем примере
        $result = $this->client->users()->show()->skipCache()->set(id: '123')->send()->resolved();
        
        // 4. Групповые запросы тоже поддерживают skipCache
        // Если на любом элементе цепи вызван skipCache() - вся группа пропустит кеш
    }

    /**
     * Пример 5: Приоритеты управления кешированием
     */
    public function cachingPriorities()
    {
        // Приоритет 1 (самый высокий): skipCache() на экземпляре
        $user1 = $this->client->users()->show()
            ->set(id: '123')
            ->skipCache()        // Абсолютный приоритет - не кешировать
            ->send()->resolved();
        
        // Приоритет 2: skipCache(false) - принудительное обновление кеша
        $this->client->cacheRequests(false); // Глобально выключено
        $user2 = $this->client->users()->show()
            ->set(id: '123') 
            ->skipCache(false)   // Принудительно обновить кеш
            ->send()->resolved(); // HTTP-запрос + обновление кеша
        
        // Приоритет 3: #[Cacheable] атрибут на классе
        // (UserListRequest имеет #[Cacheable(enabled: true)])
        
        // Приоритет 4 (самый низкий): глобальное управление cacheRequests()
        $this->client->cacheRequests(true); // Базовая настройка
    }

    /**
     * Пример 6: Приоритеты TTL (время жизни кеша) - НОВОЕ!
     */
    public function ttlPriorities()
    {
        // Установим базовый TTL на клиенте
        $this->client->requestCacheTtl(3600); // 1 час по умолчанию
        
        // Приоритет 1: getCacheTtl() в запросе (если реализует CacheableRequestInterface)
        $user1 = $this->client->users()->show()->set(id: '123')->send()->resolved(); 
        // ↳ Если UserShowRequest::getCacheTtl() возвращает 300 - будет 5 минут
        
        // Приоритет 2: #[Cacheable(ttl: ...)] атрибут
        $users = $this->client->users()->list()->set(page: 1)->send()->resolved();
        // ↳ UserListRequest имеет #[Cacheable(ttl: 1800)] - будет 30 минут
        
        // Приоритет 3: clientDTO->cacheTtl() - наша настройка!
        // Для запросов БЕЗ собственного TTL будет использоваться 3600 секунд (1 час)
        
        // Можно изменить TTL динамически
        $this->client->requestCacheTtl(7200); // Теперь 2 часа для новых запросов
        
        // Приоритет 4: CacheConfig::getDefaultTtl() - если не установлено ничего
    }
}

// ============================================
// 6. ИСПОЛЬЗОВАНИЕ В КОНТРОЛЛЕРЕ
// ============================================

class UserController
{
    public function index(\Illuminate\Http\Request $request)
    {
        $client = new ExampleClient();
        
        // Кеширование происходит автоматически на основе параметров
        return $client->users()->list()->set(
            page: $request->get('page', 1),
            per_page: $request->get('per_page', 10), 
            search: $request->get('search'),
            status: $request->get('status')
        )->send(); // Возвращает ClientResponse с кешированием
    }

    public function show(string $id, \Illuminate\Http\Request $request)
    {
        $client = new ExampleClient();
        
        return $client->users()->show()->set(
            id: $id,
            include_posts: $request->boolean('include_posts')
        )->send();
    }

    /**
     * Административная очистка кеша
     */
    public function clearCache()
    {
        $client = new ExampleClient();
        
        // Очистить весь кеш пользователей
        $client->clearRequestCacheByTags(['users']);
        
        return response()->json(['message' => 'Cache cleared']);
    }

    /**
     * Получить свежие данные (пропустив кеш) - НОВОЕ!
     */
    public function showFresh(string $id)
    {
        $client = new ExampleClient();
        
        // Всегда получать свежие данные, игнорируя кеш
        return $client->users()->show()
            ->set(id: $id)
            ->skipCache()      // Пропустить кеш
            ->send();
    }

    /**
     * Принудительно обновить кеш - НОВОЕ!
     */
    public function showUpdated(string $id)
    {
        $client = new ExampleClient();
        
        // Временно отключаем кеширование глобально
        $client->cacheRequests(false);
        
        // Но для этого запроса принудительно обновляем кеш
        return $client->users()->show()
            ->set(id: $id)
            ->skipCache(false)  // Принудительно обновить кеш (HTTP + cache)
            ->send();
    }
}
