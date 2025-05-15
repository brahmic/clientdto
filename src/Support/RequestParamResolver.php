<?php

namespace Brahmic\ClientDTO\Support;


use BackedEnum;
use Brahmic\ClientDTO\Attributes\HideFromBody;
use Brahmic\ClientDTO\Attributes\HideFromQueryStr;
use Brahmic\ClientDTO\Attributes\MapCaseOutputValue;
use ReflectionClass;
use ReflectionEnumBackedCase;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\MapOutputName;

class RequestParamResolver
{
    /**
     * @param Data $target DTO объект с параметрами запроса
     * @param PropertyContext $context Контекст: Body или QueryString
     * @return array<string, mixed> Все итоговые параметры
     */
    public function resolveRequestParams(Data $target, PropertyContext $context): array
    {
        $reflection = new ReflectionClass($target::class);
        $enumCastReplacements = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $this->getAttributes($property);

            if ($this->shouldHideProperty($attributes, $context)) {
                $target->except($property->getName(), true);
                continue;
            }

            $enumCastReplacements += $this->resolveEnumReplacements($property, $target, $attributes);
        }

        $transformed = $target->transform();

        return array_merge($transformed, $enumCastReplacements);
    }

    /**
     * @param ReflectionProperty $property
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function getAttributes(ReflectionProperty $property): \Illuminate\Support\Collection
    {
        return collect($property->getAttributes())
            ->map(fn($attr) => $attr->newInstance());
    }

    /**
     * @param \Illuminate\Support\Collection $attributes
     * @param PropertyContext $context
     * @return bool
     */
    private function shouldHideProperty(\Illuminate\Support\Collection $attributes, PropertyContext $context): bool
    {
        return ($context === PropertyContext::QueryString && $attributes->contains(fn($a) => $a instanceof HideFromQueryStr))
            || ($context === PropertyContext::Body && $attributes->contains(fn($a) => $a instanceof HideFromBody));
    }

    /**
     * @param ReflectionProperty $property
     * @param Data $target
     * @param \Illuminate\Support\Collection $attributes
     * @return array<string, mixed>
     */
    private function resolveEnumReplacements(ReflectionProperty $property, Data $target, \Illuminate\Support\Collection $attributes): array
    {
        $type = $property->getType();
        if (!$type || !is_subclass_of($typeName = $type->getName(), BackedEnum::class)) {
            return [];
        }

        if (!$property->isInitialized($target)) {
            return [];
        }

        $caseInstance = $property->getValue($target);
        $mapOutputName = $attributes->first(fn($a) => $a instanceof MapOutputName);

        $cases = (new \ReflectionEnum($typeName))->getCases();
        $reflectionEnumBackedCases = collect($cases);

        return $reflectionEnumBackedCases
            ->mapWithKeys(function (ReflectionEnumBackedCase $reflectionCase) use ($caseInstance, $mapOutputName, $property) {
                /** @var BackedEnum $enumCase */
                $enumCase = $reflectionCase->getValue();

                if ($caseInstance instanceof BackedEnum && $caseInstance->value === $enumCase->value) {
                    $mapOutputFilterValue = $reflectionCase->getAttributes(MapCaseOutputValue::class);
                    if (!empty($mapOutputFilterValue)) {
                        return [
                                $mapOutputName?->output ?? $property->getName() =>
                                $mapOutputFilterValue[0]->newInstance()->output
                        ];
                    }
                }

                return [];
            })
            ->filter()
            ->toArray();
    }
}

