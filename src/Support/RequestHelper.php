<?php

namespace Brahmic\ClientDTO\Support;

use BackedEnum;
use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Brahmic\ClientDTO\Attributes\MapCaseOutputValue;
use Illuminate\Contracts\Support\Arrayable;
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

        $enumCastReplacements = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $this->getAttributes($property);

            $hideFromQueryStr = $attributes->contains(
                fn(object $attribute) => $attribute instanceof HideFromQueryStr
            );

            $hideFromBody = $attributes->contains(
                fn(object $attribute) => $attribute instanceof HideFromBody
            );

            /** @var MapOutputName $mapOutputName */
            $mapOutputName = $attributes->first(
                fn(object $attribute) => $attribute instanceof MapOutputName
            );


            //if ($prop instanceof BackedEnum::class) {
            if (is_subclass_of($property->getType()->getName(), BackedEnum::class)) {

                $reflectionEnumBackedCases = new \ReflectionEnum($property->getType()->getName())->getCases();

                $enumCastReplacements = collect($reflectionEnumBackedCases)
                    ->mapWithKeys(function (\ReflectionEnumBackedCase $reflectionCase) use ($target, $property, $mapOutputName) {

                        /** @var BackedEnum $caseInstance */
                        if ($caseInstance = $property->getValue($target)) {
                            /** @var BackedEnum $enumCase */
                            $enumCase = $reflectionCase->getValue();

                            if ($caseInstance->value === $enumCase->value) {

                                $mapOutputFilterValue = $reflectionCase->getAttributes(MapCaseOutputValue::class);

                                if (!empty($mapOutputFilterValue)) {
                                    return [$mapOutputName->output ?? $property->getName()=> $mapOutputFilterValue[0]->newInstance()->output];
                                }
                            }

                        }

                        /*
                         * ELSE
                         * In this case, it means that the DTO object property either doesn't have a default value or hasn't been set.
                         */

                        return [];
                    })
                    ->filter()
                    ->toArray();

            }

            if (($context === PropertyContext::Body && $hideFromBody) || ($context === PropertyContext::QueryString && $hideFromQueryStr)) {
                $target->except($property->getName(), true);
            }
        }

        $transformed = $target->transform();

        return array_merge($transformed, $enumCastReplacements);
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
