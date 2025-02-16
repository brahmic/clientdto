<?php

namespace Brahmic\ClientDTO\Support;

use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Data;

class RequestHelper
{
    private static ?RequestHelper $instance = null;

    public function makeQueryString(array|Collection $queryParams, bool $hasQuestion = true): ?string
    {
        if ($queryParams instanceof Collection) {
            $queryParams = $queryParams->toArray();
        }

        $queryString = (!empty($queryParams) ? http_build_query($queryParams) : null);

        return $queryString ? ($hasQuestion ? '?' : null) . $queryString : null;
    }

    public function fill(object $target, object|array $data, bool $filter = false): mixed
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
        $reflection = new \ReflectionClass($target::class);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $this->getAttributes($property);

            $hideFromQueryStr = $attributes->contains(
                fn(object $attribute) => $attribute instanceof HideFromQueryStr
            );

            $hideFromBody = $attributes->contains(
                fn(object $attribute) => $attribute instanceof HideFromBody
            );

            if (($context === PropertyContext::Body && $hideFromBody) || ($context === PropertyContext::QueryString && $hideFromQueryStr)) {
                $target->except($property->getName(), true);
            }

        }

        return $target->transform();
    }


    private function setPropertyValue(object $object, $propertyName, $value, ?ReflectionProperty $property = null): void
    {
        if ($property) {
            if (is_null($value) && !$property->getType()->allowsNull()) {
                self::exception("Свойство {$property->getName()} не может быть null.");
            }
        }

        $object->{$propertyName} = $value;
    }

    private static function getProperties(string $class, ?int $filter = null): Collection
    {
        $reflectionClass = new ReflectionClass($class);

        $result = Collection::make();

        foreach ($reflectionClass->getProperties($filter) as $reflectionProperty) {
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