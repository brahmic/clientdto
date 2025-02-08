<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\DataProviderClient;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Traits\QueryParams;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\MapOutputName;

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
abstract class AbstractRequest
{
    use QueryParams;

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

    private string $requestBodyType = RequestOptions::JSON;


    public function getRequestBodyType(): string
    {
        return $this->requestBodyType;
    }


    public function __construct(private readonly DataProviderClient $dataProvider)
    {
    }

    public function send()
    {

        //$this->getQueryParams();

        dump($this->getQueryParams());
        dump($this->getQueryParamsAsString());
        dd($this->getBodyParams());

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


    public function getQueryParamsAsString()
    {
        return self::makeQueryString($this->getQueryParams());
    }

    public function getBodyParams():array
    {
        try {
            if (method_exists($this, 'bodyParams')) {
                return $this->bodyParams();
            }
        } catch (\Throwable $exception) {
            throw new Exception("Ошибка при получении body параметров в классе. " . $exception->getMessage());
        }

        return $this->getPublicPropertiesWithValues()->toArray();
    }

    final public function getQueryParams(): array
    {
        try {
            if (method_exists($this, 'queryParams')) {
                return $this->queryParams();
            }
        } catch (\Throwable $exception) {
            throw new Exception("Ошибка при получении query параметров в классе. " . $exception->getMessage());
        }

        return $this->getPublicPropertiesWithValues()->toArray();
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

    public function getDataProvider(): DataProviderClient
    {
        return $this->dataProvider;
    }

    public function setParticular(array $data): static
    {
        return self::setFrom(array_filter($data));
    }

    public function setFrom(array|Arrayable $data): static
    {
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
//
//        foreach ($properties as $property) {
//            $propertyName = $property->getName();
//            if (array_key_exists($propertyName, $data)) {
//                $value = $data[$propertyName];
//
//
//                $property->
//                $this->setPropertyValue($propertyName, $data[$propertyName]);
//            }
//        }


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


    /**
     * @return Collection<ReflectionProperty>
     */
    private function getOwnPublicProperties(): Collection
    {
        return self::getOwnProperties(ReflectionProperty::IS_PUBLIC);
    }

    private function getPublicPropertiesWithValues(): Collection
    {
        return $this->getOwnPublicProperties()
            ->mapWithKeys(function (ReflectionProperty $property, $key) {

                $attributes = $property->getAttributes(MapOutputName::class);

                if (!empty($attributes)) {
                    $key = $attributes[0]->newInstance()->output;
                }

                return [
                    $key => self::getObjectPropertyValue($this, $property),
                ];
            });
    }

    /**
     * @return Collection<ReflectionProperty>
     */
    private static function getOwnProperties(?int $filter = null): Collection
    {
        $class = new \ReflectionClass(static::class);

        $result = collect();

        foreach ($class->getProperties($filter) as $reflectionProperty) {
            if ($reflectionProperty->class === static::class) {
                $result->put($reflectionProperty->getName(), $reflectionProperty);
            }
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    private static function getObjectPropertyValue(object $obj, \ReflectionProperty $property): mixed
    {

        if ($property->isInitialized($obj)) {
            return $obj->{$property->getName()};
        }

        throw new Exception("Свойство '{$property->getName()}' в запросе '{$property->class}' должно быть инициализировано.");
    }


    private function setProperty()
    {

    }

    public static function makeQueryString(array|Collection $params, bool $noQuestion = true): ?string
    {
        $params = $params instanceof Collection ? $params : collect($params);

        $queryString = $params
            ->filter()
            ->map(function ($value, $key) {
                return $key . '=' . urlencode($value);
            })
            ->join('&');

        return $queryString ? ($noQuestion ? '?' : null) . $queryString : null;
    }
}
