<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Support\ClientResolver;
use Brahmic\ClientDTO\Support\PropertyContext;
use Brahmic\ClientDTO\Traits\QueryParams;
use Brahmic\ClientDTO\Traits\Timeout;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use ReflectionProperty;
use Spatie\LaravelData\Data;


abstract class AbstractRequest extends Data
{
    use QueryParams, Timeout;

    public const ?string URI = null;

    public const ?string DTO = null;

    public const string NAME = 'Абстрактный запрос';

    //public const string REQUEST_OPTIONS = RequestOptions::JSON;


    private string $requestBodyType = RequestOptions::JSON;

    private ?ClientDTO $clientDTO = null;

    private ?AbstractResource $resource = null;


    public function getRequestBodyType(): string
    {
        return $this->requestBodyType;
    }

    public function isGet(): bool
    {
        return $this instanceof GetRequest;
    }

    public function isPost(): bool
    {
        return $this instanceof PostRequest;
    }

    public function send()
    {

        dump($this->isPost() ? 'POST' : 'GET');
        //$this->getQueryParams();
        dump($this->getQueryParamsAsString());
        dump($this->getFinalQueryParams());
        dump($this->getFinalBodyParams());

        dd('send');

        try {


            if ($this instanceof GetRequest) {
                $this->getClientDTO()->get($this);
            }
            if ($this instanceof PostRequest) {
                $this->getClientDTO()->post($this);
            }

        } catch (\Throwable $throwable) {
            throw new Exception('Ошибка при отправке запроса');
        }

        throw new Exception('Неизвестный тип запроса');
    }


    public function getQueryParamsAsString(): ?string
    {
        return $this->makeQueryString($this->getFinalQueryParams());
    }

    final public function getFinalQueryParams(): array
    {
        return array_merge(
        // указанные в классе запроса если метод переопределён или на основе свойств класса
            $this->queryParams(),
            // параметры, которые могли быть добавлены динамически в классе запроса через другие методы
            $this->getQueryParams(),
            // параметры, которые были указаны в клиенте
            $this->getClientDTO()->getQueryParams()
        );
    }

    final public function getFinalBodyParams(): array
    {
        return array_merge(
            $this->bodyParams(),
        );
    }

    protected function queryParams(): array
    {
        return $this->resolveRequestParams(PropertyContext::QueryString);
    }

    protected function bodyParams(): array
    {
        return $this->resolveRequestParams(PropertyContext::Body);
    }

    private function resolveRequestParams(PropertyContext $context): array
    {
        $reflection = new \ReflectionClass(static::class);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $this->getAttributes($property);

            $hideFromQueryStr = $attributes->contains(
                fn(object $attribute) => $attribute instanceof HideFromQueryStr
            );

            $hideFromBody = $attributes->contains(
                fn(object $attribute) => $attribute instanceof HideFromBody
            );

            if (($context === PropertyContext::Body && $hideFromBody) || ($context === PropertyContext::QueryString && $hideFromQueryStr)) {
                $this->except($property->getName(), true);
            }

        }

        return $this->transform();
    }


    /**
     * @throws \Exception
     */
    public static function getDtoClass(): string
    {
        if (static::DTO) {

            return static::DTO;
        }

        self::exception('Не указан DTO для запроса');
    }

    public function resolveDtoClass(): string
    {
        return $this->getDtoClass();
    }

    public function getTimeout(): int
    {
        return $this->timeout ?: $this->getClientDTO()->getTimeout();
    }


    public function getUrl(): string
    {
        return $this->getClientDTO()->getBaseUrl($this->getUri());   //todo?
    }

    public static function getUri(): string
    {
        return static::URI;
    }

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getClientDTO(): ClientDTO
    {
        return $this->clientDTO = $this->clientDTO ?: ClientResolver::resolve(static::class);
    }

    public function getResource(): AbstractResource
    {
        return $this->resource = $this->resource ?: ClientResolver::resolveResource(static::class);
    }

    public function fill(object|array $data, bool $filter = false): static
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }
        if ($filter) {
            $data = array_filter($data);
        }

        $properties = $this->getOwnProperties(ReflectionProperty::IS_PUBLIC);

        $properties->each(function (ReflectionProperty $property) use (&$data) {
            $propertyName = $property->getName();

            if (array_key_exists($propertyName, $data)) {
                $this->setPropertyValue($propertyName, $data[$propertyName], $property);
            }
        });

        return $this;
    }

    /**
     * @param string $message
     * @return void
     * @throws \Exception
     */
    private static function exception(string $message): void
    {
        throw new \Exception($message . ' ' . static::class);
    }

    private function makeQueryString(array|Collection $queryParams, bool $hasQuestion = true): ?string
    {
        if ($queryParams instanceof Collection) {
            $queryParams = $queryParams->toArray();
        }

        $queryString = (!empty($queryParams) ? http_build_query($queryParams) : null);

        return $queryString ? ($hasQuestion ? '?' : null) . $queryString : null;
    }

    private function setPropertyValue($propertyName, $value, ?ReflectionProperty $property = null): void
    {
        if ($property) {
            if (is_null($value) && !$property->getType()->allowsNull()) {
                self::exception("Свойство {$property->getName()} не может быть null.");
            }
        }

        $this->{$propertyName} = $value;
    }

    /**
     * @param int|null $filter
     * @return Collection
     */
    private static function getOwnProperties(?int $filter = null): Collection
    {
        return self::getProperties(static::class, $filter);

    }

    private static function getProperties(string $class, ?int $filter = null): Collection
    {
        $class = new \ReflectionClass($class);

        $result = Collection::make();

        foreach ($class->getProperties($filter) as $reflectionProperty) {
            if ($reflectionProperty->class === $class) {
                $result->put($reflectionProperty->getName(), $reflectionProperty);
            }
        }

        return $result;
    }

    private function getAttributes(ReflectionProperty $reflectionProperty): Collection
    {
        return collect($reflectionProperty->getAttributes())
            ->filter(fn(\ReflectionAttribute $attribute) => class_exists($attribute->getName()))
            ->map(fn(\ReflectionAttribute $attribute) => $attribute->newInstance());
    }

}
