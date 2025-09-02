<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Contracts\CacheableRequestInterface;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Requests\RequestExecutor;
use Brahmic\ClientDTO\Resolver\ClientResolver;
use Brahmic\ClientDTO\ResourceScanner\Context;
use Brahmic\ClientDTO\Response\ClientResponse;
use Brahmic\ClientDTO\Response\ClientResult;
use Brahmic\ClientDTO\Response\FileResponse;
use Brahmic\ClientDTO\Response\RequestResult;
use Brahmic\ClientDTO\Support\Data;
use Brahmic\ClientDTO\Support\PropertyContext;
use Brahmic\ClientDTO\Support\RequestHelper;
use Brahmic\ClientDTO\Traits\BodyFormat;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Timeout;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;


abstract class AbstractRequest extends Data implements ClientRequestInterface, ChainInterface
{
    use QueryParams, Timeout, BodyFormat;

    public const int ATTEMPTS = 1;

    public const int ATTEMPT_DELAY = 1000;

    public const ?string URI = null;

    //public const ?string DTO = null;

    protected ?string $dto = null;

    protected bool $flatQueryParams = false;

    public const string NAME = 'Абстрактный запрос';
    public const ?string DESCRIPTION = null;

    private ?ClientDTO $clientDTO = null;

    private ?AbstractResource $resource = null;
    private bool $hasBeenExecuted = false;
    private ?ClientResponseInterface $response = null;
    private array $setterData = [];

    private array $exceptions = [];

    /**
     * Флаг пропуска кеширования для данного экземпляра запроса
     */
    private bool $skipCache = false;

    /**
     * Флаг, показывающий было ли явно вызвано управление кешированием
     */
    private bool $skipCacheExplicitlySet = false;


    /**
     * Use for grouped requests
     * @var bool
     */
    protected bool $groupedWithKeys = false;

    public function original(): array
    {
        $result = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($this);

            $result[$name] = $this->normalizeValue($value);
        }

        return $result;
    }


    public function modifyResult(RequestResult $requestResult):ClientResult
    {
        return $requestResult->getResult();
    }

    private function normalizeValue($value)
    {
        if (is_null($value)) {
            return '';
        }

        if (is_array($value)) {
            return array_map([$this, 'normalizeValue'], $value);
        }

        if ($value instanceof \UnitEnum) {
            return method_exists($value, 'value') ? (string)$value->value : (string)$value->name;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string)$value;
    }


    public function send(): ClientResponseInterface|ClientResponse
    {
        $this->hasBeenExecuted = true;

        /** @var ClientResponse $responseClass */
        $responseClass = $this->getClientDTO()->getResponseClass();

        $this->response = new $responseClass(new RequestExecutor()->execute($this));

        return $this->response;
    }

    public function hasBeenExecuted(): bool
    {
        return $this->hasBeenExecuted;
    }

    public function resolveDtoClass(): null|string|Data
    {
        return $this->dto;
    }

    public function getAttempts(): int
    {
        return static::ATTEMPTS;
    }

    public function getAttemptDelay(): int
    {
        return static::ATTEMPT_DELAY;
    }

    public function getUrl(): string
    {
        return $this->getBaseUrl($this->getUri());
    }

    public function getBaseUrl(?string $uri = ''): string
    {
        return $this->getClientDTO()->getBaseUrl($uri);
    }

    public static function getUri(): ?string
    {
        return static::URI;
    }

    public static function getName(): string
    {
        return static::NAME;
    }

    public static function getDescription(): ?string
    {
        return static::DESCRIPTION;
    }

    public function getClientDTO(): ClientDTO
    {
        return $this->clientDTO = $this->clientDTO ?: self::resolveClientDto();
    }

    public static function resolveClientDto(): ClientDTO
    {
        return ClientResolver::resolveClientDto(static::class);
    }

    public function queryParams(): array
    {
        return RequestHelper::getInstance()->resolveRequestParams($this, PropertyContext::QueryString);
    }

    public function bodyParams(): array
    {
        return RequestHelper::getInstance()->resolveRequestParams($this, PropertyContext::Body);
    }

    /**
     * @param object|array $data
     * @param bool $filter
     * @return $this
     */
    public function assign(object|array $data, bool $filter = false): static
    {
        return RequestHelper::getInstance()->assign($this, $data, $filter);
    }

    /**
     * @param array|Request $data
     * @return $this
     */
    public function assignFromRequest(array|Request $data): static
    {
        $this->assign($data instanceof Request ? $data->all() : $data, true);

        return $this;
    }


    public function assignSetValues(): static
    {
        $this->setterData = $this->getSetMethodArgumentValues();

        return $this->assign($this->setterData, true);
    }

    public function getSetterData(): array
    {
        return $this->setterData;
    }

    public function getSetMethodArgumentValues(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        $caller = $trace[2]; // method `set()`

        $method = new \ReflectionMethod($caller['class'], $caller['function']);
        $params = $method->getParameters();

        $argsWithNames = [];
        foreach ($params as $index => $param) {
            $name = $param->getName();
            $argsWithNames[$name] = $caller['args'][$index] ?? null;
        }
        return $argsWithNames;
    }

    public function validateRequest(): array|Arrayable
    {
        return $this::validate(get_object_vars($this));
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getMethod(): string
    {
        return match (true) {
            is_subclass_of($this, GetRequest::class) => 'get',
            is_subclass_of($this, PostRequest::class) => 'post',
            default => throw new \Exception('Unknown request type'),
        };
    }

    public function getResponseClass(): string
    {
        return $this->getClientDTO()->getResponseClass();
    }

    public function getResponse(): ?ClientResponseInterface
    {
        return $this->response;
    }

    /**
     * *** Attention! ***
     *
     * Now if DTO is specified, it is assumed that the server response is expected in JSON.
     * It is possible that in this case a different response may be expected,
     * which should be converted into a DTO object. In this case, the verification
     * method should be reviewed.
     *
     * Alternatively, this can be implemented by some kind of declaration,
     * in case the DTO is specified, but the data is expected in text form, for example.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        return !is_null($this->resolveDtoClass());
    }

    public function isDebug(): bool
    {
        return $this->getClientDTO()->isDebug();
    }

    /**
     * @return Collection<ChainInterface>
     */
    public function getChain(): Collection
    {
        return ClientResolver::getFullyInstantiatedChainOfRequest($this);
    }

    public function isFlatQueryParams(): bool
    {
        return $this->flatQueryParams;
    }

    public function debugInfo(): array
    {
        return [
            'class' => $this::class,
            'baseUrl' => $this->getBaseUrl(),
            'url' => $this->getUrl(),
            'data' => $this->toArray(),
        ];
    }

    private static function make(mixed $data): mixed
    {
        return self::validateAndCreate($data);
    }

    public function getDeclaration():array
    {
        return ClientResolver::getInstance()->determineResourceMap(static::class)->getRequestDeclaration(static::class);
    }

    /**
     * Generates a string query key that can be used later in the API.
     * It is possible to restore the class name using the string key.
     *
     * @param Collection $chain
     * @return string
     */
    public static function makeKey(Collection $chain): string
    {
        return $chain
            ->map(fn($class) => class_basename($class))
            ->push(class_basename(static::class))
            ->map(fn($val) => lcfirst($val))
            ->implode('-');
    }

    public static function declare(Context $context): array
    {
        return [
            'name' => static::getName(),
            'key' => static::makeKey($context->chain),
        ];
    }

    public function getOwnPublicProperties(): array
    {
        $reflection = new \ReflectionClass($this);
        $props = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                $props[$property->getName()] = $this->{$property->getName()};
            }
        }
        return $props;
    }

    public function getRequestClasses(): Collection
    {
        return collect();
    }

    public function getKey()
    {
        return $this->getDeclaration()['key'];
    }

    // =====================================================
    // Методы для кеширования (CacheableRequestInterface)
    // =====================================================

    /**
     * Время жизни кеша в секундах
     * null - использовать TTL по умолчанию
     */
    public function getCacheTtl(): ?int
    {
        return null;
    }

    /**
     * Теги для группировки кеша
     * Используются для групповой инвалидации
     */
    public function getCacheTags(): array
    {
        return [];
    }

    /**
     * Должен ли данный результат кешироваться
     * По умолчанию кешируем всё кроме FileResponse
     */
    public function shouldCache(mixed $resolved): bool
    {
        return !($resolved instanceof FileResponse);
    }

    // =====================================================
    // Методы управления кешированием на уровне экземпляра
    // =====================================================

    /**
     * Управление кешированием для данного запроса
     * 
     * @param bool $skip true - пропустить кеш полностью, false - принудительно обновить кеш
     * @return $this
     */
    public function skipCache(bool $skip = true): self
    {
        $this->skipCache = $skip;
        $this->skipCacheExplicitlySet = true;
        return $this;
    }

    /**
     * Проверить, должен ли запрос пропускать кеширование
     * 
     * @return bool
     */
    public function shouldSkipCache(): bool
    {
        return $this->skipCache;
    }

    /**
     * Проверить, нужно ли принудительно обновить кеш (skipCache(false))
     * 
     * @return bool
     */
    public function isForceCacheEnabled(): bool
    {
        return $this->skipCacheExplicitlySet && !$this->skipCache;
    }
}
