<?php

namespace Brahmic\ClientDTO\Support\Factories;

use Brahmic\ClientDTO\Attributes\Hidden;
use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Brahmic\ClientDTO\Attributes\MapName;
use Brahmic\ClientDTO\Support\RequestDataProperty;
use Closure;
use Exception;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionProperty;
use Throwable;

readonly class RequestDataPropertyFactory
{

    public function __construct(public object $object)
    {

    }

    /**
     * @throws Exception
     */
    public function make(ReflectionProperty $reflectionProperty): RequestDataProperty
    {
        $value = $this->getObjectPropertyValue($reflectionProperty, function (mixed $value) {

            if (is_null($value)) return $value;

            if (is_string($value)) {
                return $value;
            }

            if ($this->canBeString($value)) {
                return (string)$value;
            }

            return $value;
        });

        $attributes = $this->getAttributes($reflectionProperty);

        $hidden = $attributes->contains(
            fn(object $attribute) => $attribute instanceof Hidden
        );

        $hideFromBody = $attributes->contains(
            fn(object $attribute) => $attribute instanceof HideFromBody
        );

        $hideFromQueryStr = $attributes->contains(
            fn(object $attribute) => $attribute instanceof HideFromQueryStr
        );


        $mapOutputName = $attributes->first(function (object $attribute) {
            return $attribute instanceof MapName;
        });

        $name = $mapOutputName ? $mapOutputName->output : $reflectionProperty->getName();

        //$value = $attributes->first(fn (object $attribute) => $attribute instanceof CanCast)?->get();


        return new RequestDataProperty(
            name: $name,
            value: $value,
            hidden: $hidden,
            hideFromBody: $hideFromBody,
            hideFromQueryStr: $hideFromQueryStr,
        );
    }

    private function getAttributes(ReflectionProperty $reflectionProperty): Collection
    {
        return collect($reflectionProperty->getAttributes())
            ->filter(fn(ReflectionAttribute $attribute) => class_exists($attribute->getName()))
            ->map(fn(ReflectionAttribute $attribute) => $attribute->newInstance());
    }

    /**
     * @param ReflectionProperty $property
     * @param Closure|null $closure
     * @return mixed
     * @throws Exception
     */
    private function getObjectPropertyValue(ReflectionProperty $property, ?Closure $closure = null): mixed
    {
        if ($property->isInitialized($this->object)) {

            $value = $this->object->{$property->getName()};

            if (method_exists($this->object, $property->getName())) {
                $value = $this->object->{$property->getName()}($value);
            }

            return $closure ? $closure($value, $property) : $value;
        }

        throw new Exception("Свойство '{$property->getName()}' в запросе '{$property->class}' должно быть инициализировано.");
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