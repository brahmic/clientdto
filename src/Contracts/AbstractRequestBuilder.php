<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Brahmic\ClientDTO\RemoteResourceProvider;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Support\Factories\RequestDataPropertyFactory;
use Brahmic\ClientDTO\Support\PropertyContext;
use Brahmic\ClientDTO\Support\RequestDataProperty;
use Brahmic\ClientDTO\Support\PropertyCollection;
use Brahmic\ClientDTO\Traits\CustomQueryParams;
use Closure;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Concerns\TransformableData;
use Spatie\LaravelData\Contracts\BaseData as BaseDataContract;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Factories\DataClassFactory;
use Spatie\LaravelData\WithData;
use Throwable;

/**
 * Для GET запросов
 * Все публичные свойства считаются query параметром, если не определён метод queryParams();
 * в противном случае query параметры будут извлечены через метод queryParams()
 *
 * Для POST запросов
 * Все публичные свойства автоматически считаются body, если не определён метод bodyParams();
 * в противном случае query параметры будут извлечены через метод bodyParams()
 *
 * Если указан метод queryParams(), то эти свойства будут использованы для
 * формирования query и проигнорированы в body, если он буден собран автоматически.
 *
 *
 * Поддерживает атрибуты:
 * #[MapOutputName('new_name')]
 */
abstract class AbstractRequestBuilder extends Data
{
    use CustomQueryParams;

    public const ?string URI = null;

    public const ?string DTO = null;

    public const string NAME = 'Абстрактный запрос';

    public const string REQUEST_OPTIONS = RequestOptions::JSON;

    /**
     * Если null, будет проинициализировано значением по умолчанию из Client
     *
     * @var int|null
     */
    protected ?int $timeout = null;
    protected ?RemoteResourceProvider $dataProvider = null;

    private string $requestBodyType = RequestOptions::JSON;


    public function getRequestBodyType(): string
    {
        return $this->requestBodyType;
    }




    public function send()
    {
        dump($this->isPostRequest() ? 'POST' : 'GET');
        //$this->getQueryParams();

        dump('=====[   getQueryParamsAsString');
        dump($this->getQueryParamsAsString());
        dump('=====[   getQueryParams');
        dump($this->getQueryParams());
        dump('=====[   getBodyParams');
        dump($this->getBodyParams());
        dd('send');

        try {


            if ($this instanceof GetRequest) {
                $this->getDataProvider()->get($this);
            }
            if ($this instanceof PostRequest) {
                $this->getDataProvider()->post($this);
            }

        } catch (\Throwable $throwable) {
            throw new Exception('Ошибка при отправке запроса');
        }

        throw new Exception('Неизвестный тип запроса');
    }


    public function getQueryParamsAsString(): ?string
    {
        return $this->makeQueryString($this->getQueryParams());
    }

    final public function getQueryParams(): array
    {
        return array_merge(
        // указанные в классе запроса если метод переопределён или на основе свойств класса
            $this->queryParams(),
            // параметры, которые могли быть добавлены динамически в классе запроса через другие методы
            $this->getCustomQueryParams(),
            // параметры, которые были указаны в клиенте
            $this->getDataProvider()->getCustomQueryParams()
        );
    }

    final public function getBodyParams(): array
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
        return $this->timeout ?: $this->getDataProvider()->getTimeout();
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->getDataProvider()->getBaseUrl($this->getUri());   //todo?
    }

    public static function getUri(): string
    {
        return static::URI;
    }

    public static function getName(): string
    {
        return static::NAME;
    }

    public function getDataProvider(): RemoteResourceProvider
    {
        return $this->dataProvider;
    }

    public function setDataProvider(RemoteResourceProvider $dataProvider): static
    {
        $this->dataProvider = $dataProvider;
        return $this;
    }

    public function setParticular(array $data): static
    {
        return self::setFrom(array_filter($data));
    }

    public function setFrom(BaseDataContract|Arrayable|array $data): static
    {
        return self::from($data);
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        $properties = $this->getOwnPublicProperties();

        $properties->each(function (ReflectionProperty $property) use (&$data) {
            $propertyName = $property->getName();

            if (array_key_exists($propertyName, $data)) {
                $this->setPropertyValue($propertyName, $data[$propertyName], $property);
            }
        });

        return $this;
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
     * @param string $message
     * @return void
     * @throws \Exception
     */
    private static function exception(string $message): void
    {
        throw new \Exception($message . ' ' . static::class);
    }


//    private function getPublicPropertiesWithValues(PropertyContext $context): Collection
//    {
//        //$dataClass = app(DataClassFactory::class)->build(new ReflectionClass(static::class));
//
//
//        //$dataClass = app(DataClassFactory::class)->build(new ReflectionClass(static::class));
//
//        $reflection = new \ReflectionClass(static::class);
//
//
//        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
//            $attributes = $this->getAttributes($property);
//
//            $hideFromQueryStr = $attributes->contains(
//                fn(object $attribute) => $attribute instanceof HideFromQueryStr
//            );
//
//            $hideFromBody = $attributes->contains(
//                fn(object $attribute) => $attribute instanceof HideFromBody
//            );
//
//            if (($context === PropertyContext::Body && $hideFromBody) || ($context === PropertyContext::QueryString && $hideFromQueryStr)) {
//                $this->except($property->getName(), true);
//            }
//
//            foreach ($property->getAttributes() as $attribute) {
//                dump($attribute->getName());
//            }
//        }
//
//        dd($this->toArray());
//
//
////
////        $dataClass->properties->each(function (DataProperty $property) use (&$data) {
////           dump($property->hidden);
////        });
////
////        $result = $dataClass->properties;
//
//        //$this->except('regions', true);
//
//        //dd($this->toArray());
//
//        //dd($this->exceptWhen([234, 234]));
//        return $this->getOwnPublicProperties()
//            ->map(function (ReflectionProperty $property) {
//                return new RequestDataPropertyFactory($this)->make($property);
//            })
//            ->filter(function (RequestDataProperty $requestDataProperty) use ($context) {
//
//                return
//                    !$requestDataProperty->hidden
//                    && !($context === PropertyContext::QueryString && $requestDataProperty->hideFromQueryStr)
//                    && !($context === PropertyContext::Body && $requestDataProperty->hideFromBody);
//
//            })
//            ->mapWithKeys(function (RequestDataProperty $requestDataProperty) {
//                return [$requestDataProperty->name => $requestDataProperty->value,];
//            });
//    }

    private function makeQueryString(array|Collection $queryParams, bool $hasQuestion = true): ?string
    {
        if ($queryParams instanceof Collection) {
            $queryParams = $queryParams->toArray();
        }

        $queryString = (!empty($queryParams) ? http_build_query($queryParams) : null);

        return $queryString ? ($hasQuestion ? '?' : null) . $queryString : null;
    }


    private function isMethodOverridden(string $methodName): bool
    {
        $reflectionClass = new ReflectionClass(static::class);
        $parentClass = $reflectionClass->getParentClass();

        if (!$parentClass) {
            return false; // Нет родительского класса
        }

        if (!$parentClass->hasMethod($methodName)) {
            return false; // Метод отсутствует в родительском классе
        }

        $method = $reflectionClass->getMethod($methodName);
        $parentMethod = $parentClass->getMethod($methodName);

        // Сравниваем, где объявлен метод
        return $method->getDeclaringClass()->getName() !== $parentMethod->getDeclaringClass()->getName();
    }

    protected function isQueryParamsOverride(): bool
    {
        return $this->isMethodOverridden('queryParams');
    }

//    protected function isBodyParamsOverride(): bool
//    {
//        return $this->isMethodOverridden('bodyParams');
//    }


    /**
     * @return Collection<ReflectionProperty>
     */
    private function getOwnPublicProperties(): Collection
    {
        return self::getOwnProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * @return PropertyCollection<ReflectionProperty>
     */
    private static function getOwnProperties(?int $filter = null): PropertyCollection
    {
        $class = new \ReflectionClass(static::class);

        $result = PropertyCollection::make();

        foreach ($class->getProperties($filter) as $reflectionProperty) {
            if ($reflectionProperty->class === static::class) {
                $result->put($reflectionProperty->getName(), $reflectionProperty);
            }
        }

        return $result;
    }

    public function isGetRequest(): bool
    {
        return $this instanceof GetRequest;
    }

    public function isPostRequest(): bool
    {
        return $this instanceof PostRequest;
    }

    private function getAttributes(ReflectionProperty $reflectionProperty): Collection
    {
        return collect($reflectionProperty->getAttributes())
            ->filter(fn(\ReflectionAttribute $attribute) => class_exists($attribute->getName()))
            ->map(fn(\ReflectionAttribute $attribute) => $attribute->newInstance());
    }
}
