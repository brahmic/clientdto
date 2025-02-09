<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\DataProviderClient;
use Brahmic\ClientDTO\Requests\GetRequest;
use Brahmic\ClientDTO\Requests\PostRequest;
use Brahmic\ClientDTO\Traits\CustomQueryParams;
use Closure;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\MapOutputName;
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
abstract class AbstractRequest
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

        dump($this->getQueryParamsAsString());
        dump($this->getQueryParams());
        dd($this->bodyParams());

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

//    public function getBodyParams(): array
//    {
//        try {
//            if (method_exists($this, 'bodyParams')) {
//                return $this->bodyParams();
//            }
//        } catch (\Throwable $exception) {
//            throw new Exception("Ошибка при получении body параметров в классе. " . $exception->getMessage());
//        }
//
//        return $this->getPublicPropertiesWithValues()->toArray();
//    }
//
//    /**
//     * @throws Exception
//     */
//    private function getCustomQueryParams(): Collection
//    {
//        try {
//            if (method_exists($this, 'queryParams')) {
//                return collect($this->queryParams());
//            }
//        } catch (\Throwable $exception) {
//            throw new Exception("Ошибка при получении query параметров в классе. " . $exception->getMessage());
//        }
//
//        return collect();
//    }

    private function getParamsFromProperties(): Collection
    {
        return $this->getPublicPropertiesWithValues(function (ReflectionProperty $property, mixed $value) {
            // можно обработать/кастовать к строке значение

            /*
             * Если можно привести к строке — приводим.
             * Если указан атрибут кастования — кастуем.
             */
            return $this->canBeString($value) ? $value : '111';
        });
    }

    protected function queryParams(): array
    {
        return $this->getParamsFromProperties()->toArray();
    }

    protected function bodyParams(): array
    {
        /*
         * todo
         * Если в запросе определён метод queryParams(), то перечисленные там свойства
         * нужно исключить из body;
         */

        return $this->getParamsFromProperties()->toArray();
    }

    final public function getQueryParams(): array
    {
        return array_merge(
            $this->queryParams(),
            $this->customQueryParams,
            $this->getDataProvider()->getCustomQueryParams()
        );
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

    private function getPublicPropertiesWithValues(?Closure $closure = null): Collection
    {
        return $this->getOwnPublicProperties()
            ->mapWithKeys(function (ReflectionProperty $property, $key) use ($closure) {

                $attributes = $property->getAttributes(MapOutputName::class);

                if (!empty($attributes)) {
                    $key = $attributes[0]->newInstance()->output;
                }

                return [
                    $key => self::getObjectPropertyValue($this, $property, $closure),
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
    private static function getObjectPropertyValue(object $obj, ReflectionProperty $property, ?Closure $closure = null): mixed
    {
        if ($property->isInitialized($obj)) {

            $value = $obj->{$property->getName()};

            return $closure ? $closure($property, $value) : $value;
        }

        throw new Exception("Свойство '{$property->getName()}' в запросе '{$property->class}' должно быть инициализировано.");
    }

    public function makeQueryString(array|Collection $queryParams, bool $noQuestion = true): ?string
    {
        if ($queryParams instanceof Collection) {
            $queryParams = $queryParams->toArray();
        }

        $queryString = (!empty($queryParams) ? http_build_query($queryParams) : null);

        return $queryString ? ($noQuestion ? '?' : null) . $queryString : null;
    }

    private function canBeString($var): bool
    {
        try {
            return is_scalar($var) || (is_object($var) && method_exists($var, '__toString')) || (string)$var !== null;
        } catch (Throwable $e) {
            return false;
        }
    }
}
