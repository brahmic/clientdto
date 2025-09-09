# Глубокий аудит ClientDTO - Механизм кеширования
*Анализ архитектуры и план реализации кеширования с graceful degradation*

## 🎯 Задача
Добавить механизм кеширования в ClientDTO с требованиями:
- **#[Cachable]** атрибут для запросов 
- **cacheRequests(bool)** глобальное управление (по умолчанию false)
- **cacheRaw(bool)** уровень кеширования (по умолчанию true) 
- **skipCache()** игнорирование кеша на уровне запроса
- **saveRawAs()** сохранение сырых данных в ClientResponse
- **Graceful degradation** - ошибки кеша не должны ломать функционал

## 📋 Анализ текущей архитектуры

### 🔄 Поток выполнения запроса

#### 1. **AbstractRequest::send()** (строки 106-116)
```php
public function send(): ClientResponseInterface|ClientResponse
{
    $this->hasBeenExecuted = true;
    
    /** @var ClientResponse $responseClass */
    $responseClass = $this->getClientDTO()->getResponseClass();
    
    $this->response = new $responseClass(new RequestExecutor()->execute($this));
    
    return $this->response;
}
```

#### 2. **RequestExecutor::execute()** (строки 83-138)
- Создает ExecutiveRequest
- Выполняет sendRequest()
- Обрабатывает все исключения через специализированные handlers
- **ВСЕГДА** возвращает RequestResult

#### 3. **RequestExecutor::sendRequest()** (строки 184-202)  
```php
public function sendRequest(): PromiseInterface|Response
{
    $this->isAttemptNeeded = false;
    
    $this->response = $this->executiveRequest->send();         // HTTP запрос
    $this->response->throwIfClientError()->throwIfServerError();
    $this->nextAttempt();
    
    if ($this->response->successful()) {
        $this->handleSuccessfulResponse();                      // ResponseDtoResolver
    } else {
        $this->handleUnsuccessfulResponse();
    }
    
    return $this->response;
}
```

#### 4. **ResponseDtoResolver::resolve()** (строки 45-87)
- Проверяет успешность ответа
- Определяет тип данных (файл/JSON/текст)
- Создает DTO объекты или FileResponse
- Вызывает postProcess() если определен

#### 5. **RequestResult** - контейнер данных
- resolved: преобразованные данные (DTO/FileResponse)
- response: HTTP ответ Laravel
- clientRequest: исходный запрос
- log/message/statusCode/details: метаинформация

#### 6. **ClientResponse** - финальный результат 
- Инкапсулирует RequestResult
- Предоставляет API для доступа к данным
- raw(), resolved(), toArray(), toResponse()

### 🔍 Ключевые особенности архитектуры

#### ✅ **Принципы обработки ошибок:**
1. **Централизованный try-catch** в execute()
2. **Специализированные handlers** для каждого типа ошибки
3. **setResponseStatus()** - единая точка установки статуса
4. **Graceful degradation** - всегда валидный ответ
5. **Debug-aware** - детали только в режиме отладки

#### ✅ **Архитектурные преимущества:**
1. **Четкое разделение ответственности** между компонентами
2. **Неинвазивность** - каждый компонент изолирован  
3. **Расширяемость** - легко добавлять новую логику
4. **Надежность** - исключения не прерывают работу

---

## 🏗️ Проектирование кеширования

### 🎯 **Точки интеграции**

#### 1. **В RequestExecutor::sendRequest()** - ДО HTTP запроса
```php
// 🟢 Проверка кеша ПЕРЕД HTTP запросом
$cachedData = $this->tryGetFromCache($this->clientRequest);
if ($cachedData !== null) {
    $this->resolved = $cachedData;
    $this->setResponseStatus(200, 'From cache');
    return null; // Пропускаем HTTP запрос
}
```

#### 2. **В RequestExecutor::handleSuccessfulResponse()** - ПОСЛЕ обработки ответа  
```php
// 🟢 Сохранение в кеш ПОСЛЕ успешного разбора
if ($this->response->successful()) {
    $this->handleSuccessfulResponse();
    
    // Кеширование
    $this->tryStoreInCache($this->clientRequest, $this->resolved, $this->response->body());
}
```

### 🧩 **Компоненты кеширования**

#### 1. **Атрибут Cachable** (/src/Attributes/Cachable.php)
```php
#[Attribute(Attribute::TARGET_CLASS)]
class Cachable
{
    public function __construct(
        public bool $enabled = true,
        public ?int $ttl = null
    ) {}
}
```

#### 2. **CacheManager** (/src/Cache/CacheManager.php)
```php
class CacheManager
{
    // Проверка настроек кеширования с приоритетами
    public function shouldUseCache(AbstractRequest $request): bool;
    public function shouldStoreCache(AbstractRequest $request): bool;
    
    // Операции с кешем (с graceful degradation)
    public function get(AbstractRequest $request): ?CachedResponse;
    public function store(AbstractRequest $request, mixed $resolved, ?string $rawData): void;
    
    // Генерация ключей кеша
    public function buildKey(AbstractRequest $request): string;
    
    // Вспомогательные методы
    private function getCachableAttribute(AbstractRequest $request): ?Cachable;
}
```

#### 3. **CachedResponse** (/src/Cache/CachedResponse.php)
```php
class CachedResponse
{
    public function __construct(
        public readonly mixed $resolved,      // DTO объект или данные
        public readonly bool $isRaw,          // Тип кеша: RAW/DTO  
        public readonly ?string $rawData      // Сырые данные для raw()
    ) {}
}
```

### 🔧 **Методы API**

#### ClientDTO расширения:
```php
// Добавить в ClientDTO:
private bool $requestCacheEnabled = false;
private bool $rawCacheEnabled = true;

public function cacheRequests(bool $enabled = true): static
{
    $this->requestCacheEnabled = $enabled;
    return $this;
}

public function cacheRaw(bool $enabled = true): static
{
    $this->rawCacheEnabled = $enabled; 
    return $this;
}

public function isRequestCacheEnabled(): bool { return $this->requestCacheEnabled; }
public function isRawCacheEnabled(): bool { return $this->rawCacheEnabled; }
```

#### AbstractRequest расширения:
```php
// Добавить в AbstractRequest:
private bool $skipCacheFlag = false;

public function skipCache(): static 
{
    $this->skipCacheFlag = true;
    return $this;
}

public function shouldSkipCache(): bool
{
    return $this->skipCacheFlag;
}
```

#### ClientResponse расширения:
```php
// Добавить в ClientResponse:
public function saveRawAs(?string $path = null): bool
{
    $rawData = $this->raw();
    
    if ($rawData === null) {
        throw new \InvalidArgumentException('No raw data available');
    }
    
    if ($path === null) {
        $path = storage_path('app/cache_dumps/' . now()->format('Y-m-d_H-i-s') . '.json');
    } else {
        // Относительные пути от корня приложения
        $path = base_path(ltrim($path, '/'));
    }
    
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    return file_put_contents($path, $rawData) !== false;
}
```

---

## 🚦 Логика принятия решений

### **Приоритеты настроек кеширования:**
```
глобальный (ClientDTO) < атрибуты (#[Cachable]) < методы запроса (skipCache)
```

#### CacheManager::shouldUseCache()
```php
public function shouldUseCache(AbstractRequest $request): bool
{
    // 1. Метод запроса (САМЫЙ ВЫСОКИЙ приоритет)
    if ($request->shouldSkipCache()) {
        return false;
    }
    
    // 2. Атрибут класса запроса  
    $cachable = $this->getCachableAttribute($request);
    if ($cachable && !$cachable->enabled) {
        return false;
    }
    
    // 3. Глобальная настройка клиента (САМЫЙ НИЗКИЙ приоритет) 
    return $request->getClientDTO()->isRequestCacheEnabled();
}
```

#### CacheManager::shouldStoreCache()
```php
public function shouldStoreCache(AbstractRequest $request): bool
{
    // skipCache() влияет только на ЧТЕНИЕ, НЕ на запись!
    
    // 1. Атрибут класса запроса
    $cachable = $this->getCachableAttribute($request);
    if ($cachable && !$cachable->enabled) {
        return false;
    }
    
    // 2. Глобальная настройка клиента
    return $request->getClientDTO()->isRequestCacheEnabled();
}
```

### **Уровни кеширования:**

#### А) cacheRaw(true) - кеширование RAW данных
```php
// Сохраняем сырой HTTP ответ
$cacheManager->store($request, $resolved, $response->body());

// При чтении воссоздаем поток обработки 
$cachedResponse = $cacheManager->get($request);
if ($cachedResponse->isRaw) {
    // Эмулируем HTTP ответ и пропускаем через ResponseDtoResolver
    $mockResponse = $this->createMockResponse($cachedResponse->rawData);
    $this->resolved = new ResponseDtoResolver($request, $mockResponse)->resolve();
}
```

#### Б) cacheRaw(false) - кеширование DTO объектов
```php
// Сохраняем готовый DTO объект
$cacheManager->store($request, $resolved, null);

// При чтении используем напрямую
$cachedResponse = $cacheManager->get($request);  
if (!$cachedResponse->isRaw) {
    $this->resolved = $cachedResponse->resolved;
}
```

### **Генерация ключей кеша:**
```php
public function buildKey(AbstractRequest $request): string
{
    $namespace = $request::class;
    $params = $request->original(); // Публичные свойства запроса
    
    $keyData = [
        'class' => $namespace,
        'params' => $params
    ];
    
    return 'clientdto:' . md5(serialize($keyData));
}
```

---

## 🛡️ Обработка ошибок кеширования

### **Принципы Graceful Degradation:**

#### 1. **tryGetFromCache()** - БЕЗ исключений
```php
private function tryGetFromCache(AbstractRequest $request): ?CachedResponse
{
    try {
        if (!$this->cacheManager->shouldUseCache($request)) {
            return null;
        }
        
        return $this->cacheManager->get($request);
        
    } catch (\Throwable $e) {
        // Кеш недоступен - логируем и продолжаем без кеша
        $this->log->add("Cache read failed: {$e->getMessage()}");
        
        if ($this->clientRequest->isDebug()) {
            $this->details[] = "Cache unavailable: {$e->getMessage()}";
        }
        
        return null; // = делаем HTTP запрос
    }
}
```

#### 2. **tryStoreInCache()** - БЕЗ исключений  
```php
private function tryStoreInCache(AbstractRequest $request, mixed $resolved, ?string $rawData): void
{
    try {
        if (!$this->cacheManager->shouldStoreCache($request)) {
            return;
        }
        
        $this->cacheManager->store($request, $resolved, $rawData);
        $this->log->add("Stored in cache");
        
    } catch (\Throwable $e) {
        // Не смогли закешировать - не критично, продолжаем
        $this->log->add("Cache write failed: {$e->getMessage()}");
        
        if ($this->clientRequest->isDebug()) {
            $this->details[] = "Cache storage failed: {$e->getMessage()}";
        }
        
        // НЕ бросаем исключение дальше!
    }
}
```

#### 3. **Стратегии восстановления:**
- **Кеш недоступен** → HTTP запрос
- **Кеш поврежден** → очистка + HTTP запрос
- **Кеш медленный** → timeout + HTTP запрос
- **Ошибка десериализации** → очистка записи + HTTP запрос

#### 4. **Интеграция в RequestExecutor::sendRequest():**
```php
public function sendRequest(): PromiseInterface|Response
{
    $this->isAttemptNeeded = false;

    // 🟢 Попытка получить из кеша (graceful)
    $cachedResponse = $this->tryGetFromCache($this->clientRequest);
    if ($cachedResponse !== null) {
        $this->resolved = $cachedResponse->resolved;
        $this->setResponseStatus(200, $cachedResponse->isRaw ? 'From cache (RAW)' : 'From cache (DTO)');
        
        // Для RAW кеша нет реального HTTP ответа
        return $cachedResponse->isRaw ? null : $this->response;
    }

    // 🟢 Обычный HTTP запрос
    $this->response = $this->executiveRequest->send();
    $this->response->throwIfClientError()->throwIfServerError();
    $this->nextAttempt();

    if ($this->response->successful()) {
        $this->handleSuccessfulResponse();
        
        // 🟢 Попытка сохранить в кеш (graceful)
        $this->tryStoreInCache($this->clientRequest, $this->resolved, $this->response->body());
    } else {
        $this->handleUnsuccessfulResponse();
    }

    return $this->response;
}
```

---

## 📋 Пошаговый план реализации

### **Этап 1: Создание базовых компонентов** (1-2 дня)

#### 1.1. Атрибут Cachable
- Создать `/src/Attributes/Cachable.php`
- Поддержка enabled и ttl параметров

#### 1.2. CachedResponse  
- Создать `/src/Cache/CachedResponse.php`
- Структура данных для кешированных ответов

#### 1.3. CacheManager
- Создать `/src/Cache/CacheManager.php` 
- Методы shouldUseCache/shouldStoreCache
- Логика приоритетов настроек
- Генерация ключей кеша

### **Этап 2: Расширение API** (1 день)

#### 2.1. ClientDTO методы
- `cacheRequests(bool)` - глобальное управление
- `cacheRaw(bool)` - уровень кеширования
- Геттеры для CacheManager

#### 2.2. AbstractRequest методы  
- `skipCache()` - игнорирование кеша
- `shouldSkipCache()` - проверка флага

#### 2.3. ClientResponse методы
- `saveRawAs(?string)` - сохранение сырых данных

### **Этап 3: Интеграция в RequestExecutor** (1-2 дня)

#### 3.1. Методы graceful degradation
- `tryGetFromCache()` - чтение без исключений
- `tryStoreInCache()` - запись без исключений  
- `createMockResponse()` - эмуляция HTTP ответа для RAW кеша

#### 3.2. Модификация sendRequest()
- Проверка кеша ДО HTTP запроса
- Сохранение в кеш ПОСЛЕ успешного ответа
- Интеграция с существующей системой логирования

#### 3.3. Обработка RAW vs DTO кеширования
- RAW: сохранение response->body() + воссоздание через ResponseDtoResolver  
- DTO: сохранение resolved объекта напрямую

### **Этап 4: Модификация RequestResult** (0.5 дня)

#### 4.1. Поддержка сырых данных из кеша
- Добавить поле rawData в конструктор RequestResult
- Обеспечить доступность для ClientResponse::raw()

### **Этап 5: Тестирование** (2-3 дня) 

#### 5.1. Unit тесты
- Тестирование CacheManager логики
- Тестирование приоритетов настроек  
- Тестирование graceful degradation

#### 5.2. Integration тесты
- Полный цикл кеширования RAW данных
- Полный цикл кеширования DTO объектов
- Обработка ошибок кеша
- Метод saveRawAs()

#### 5.3. Performance тесты
- Влияние на скорость запросов
- Потребление памяти при кешировании

### **Этап 6: Документация** (1 день)

#### 6.1. Обновление README.md
- Примеры использования API кеширования
- Объяснение приоритетов и уровней

#### 6.2. Создание CACHE.md
- Подробная документация механизма кеширования
- Troubleshooting и best practices

---

## ⚖️ Анализ рисков

### 🟢 **Низкие риски:**
1. **Обратная совместимость** - новые методы не ломают существующий код
2. **Graceful degradation** - кеш опционален, не влияет на основной функционал  
3. **Изолированность** - компоненты кеширования изолированы от основной логики

### 🟡 **Средние риски:**
1. **Производительность** - дополнительные операции могут замедлить запросы
2. **Память** - кеширование больших объектов может влиять на потребление RAM
3. **Сложность отладки** - кешированные ответы могут усложнить debugging

### 🔴 **Потенциальные проблемы:**
1. **Консистентность данных** - устаревшие данные в кеше
2. **Размер кеша** - большие DTO объекты могут переполнить кеш
3. **Serialization issues** - проблемы сериализации сложных объектов

### 🛡️ **Стратегии митигации:**
1. **TTL по умолчанию** для предотвращения устаревших данных
2. **Размерные ограничения** в CacheManager
3. **Подробное логирование** для отладки проблем кеша
4. **Конфигурируемые параметры** для тонкой настройки

---

## 📊 Оценка реализации

### **Время:** 6-8 рабочих дней
### **Сложность:** Средняя
### **Команда:** 1-2 разработчика

### **Критерии успеха:**
1. ✅ Все API методы работают согласно требованиям
2. ✅ Приоритеты кеширования соблюдаются  
3. ✅ Graceful degradation функционирует корректно
4. ✅ Нет регрессий в существующем функционале
5. ✅ Производительность не ухудшена значительно  
6. ✅ Покрытие тестами не менее 90%

### **Критические зависимости:**
1. Laravel Cache система должна быть настроена
2. Serialization должна поддерживать все DTO типы проекта
3. Память сервера должна быть достаточной для кеширования

---

*Анализ завершен: готов к реализации*
