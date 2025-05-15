<?php

namespace Brahmic\ClientDTO\Support;

use BackedEnum;
use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Brahmic\ClientDTO\Attributes\MapCaseOutputValue;
use DateTime;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\MapOutputName;

class RequestHelper
{
    private static ?RequestHelper $instance = null;

    public function makeQueryString(array|Collection $queryParams, $flat = false, bool $hasQuestion = true): ?string
    {
        if ($queryParams instanceof Collection) {
            $queryParams = $queryParams->toArray();
        }

        $queryString = (!empty($queryParams) ? http_build_query($queryParams) : null);

        if ($flat) {
            $queryString = preg_replace('/%5B\d+%5D/', '', $queryString); // Убираем квадратные скобки и индексы;
        }

        return $queryString ? ($hasQuestion ? '?' : null) . $queryString : null;
    }

    public function assign(object $target, object|array $data, bool $filter = false): mixed
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }
        if ($filter) {
            $data = array_filter($data);
        }

        $properties = self::getProperties($target::class, ReflectionProperty::IS_PUBLIC);

        $properties->each(function (ReflectionProperty $property) use (&$data, $target) {
            $propertyName = $property->getName();

            if (array_key_exists($propertyName, $data)) {

                $this->setPropertyValue($target, $propertyName, $data[$propertyName], $property);
            }
        });

        return $target;
    }


    public function resolveRequestParams(Data $target, PropertyContext $context): array
    {
        return new RequestParamResolver()->resolveRequestParams($target, $context);
    }

    private function setPropertyValue(object $object, $propertyName, $value, ?ReflectionProperty $property = null): void
    {
        if ($property) {
            if (is_null($value) && !$property->getType()->allowsNull()) {
                self::exception("Свойство {$property->getName()} не может быть null.");
            }

            $typeName = $property->getType()?->getName();

            if ($typeName && enum_exists($typeName) && !($value instanceof $typeName)) {
                /** @var BackedEnum $typeName  */
                $value = $typeName::from($value);
            } elseif ($typeName === Carbon::class && !($value instanceof Carbon)) {
                $value = Carbon::parse($value);
            }elseif ($typeName === DateTime::class && !($value instanceof DateTime)) {
                $value = DateTime::createFromFormat('d.m.Y',Carbon::parse($value)->format('d.m.Y'));
            }
        }

        try {
            //todo на типизированное свойство Enum не происходит присваивания
            $object->{$propertyName} = $value;
        } catch (\Throwable $exception) {

        }
    }

    private static function getProperties(string $class, ?int $filter = null): Collection
    {
        $reflectionClass = new ReflectionClass($class);

        $result = Collection::make();

        foreach ($reflectionClass->getProperties($filter) as $reflectionProperty) {
            $result->put($reflectionProperty->getName(), $reflectionProperty);
        }

        return $result;
    }

    private function getAttributes(ReflectionProperty $reflectionProperty): Collection
    {
        return collect($reflectionProperty->getAttributes())
            ->filter(fn(\ReflectionAttribute $attribute) => class_exists($attribute->getName()))
            ->map(fn(\ReflectionAttribute $attribute) => $attribute->newInstance());
    }

    private static function exception(string $message): void
    {
        throw new \Exception($message . ' ' . static::class);
    }


    public static function getInstance(): RequestHelper
    {
        if (static::$instance) {
            return static::$instance;
        }

        return static::$instance = new static();
    }
}
