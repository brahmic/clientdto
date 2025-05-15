<?php

namespace Brahmic\ClientDTO\Support;

use Arr;
use Brahmic\ClientDTO\Attributes\Append;
use Illuminate\Support\Collection;
use ReflectionProperty;
use Spatie\LaravelData\DataPipeline;
use Spatie\LaravelData\DataPipes\AuthorizedDataPipe;
use Spatie\LaravelData\DataPipes\CastPropertiesDataPipe;
use Spatie\LaravelData\DataPipes\DefaultValuesDataPipe;
use Spatie\LaravelData\DataPipes\FillRouteParameterPropertiesDataPipe;
use Spatie\LaravelData\DataPipes\MapPropertiesDataPipe;
use Spatie\LaravelData\DataPipes\ValidatePropertiesDataPipe;
use Spatie\LaravelData\Normalizers\ArrayableNormalizer;
use Spatie\LaravelData\Normalizers\ArrayNormalizer;
use Spatie\LaravelData\Normalizers\JsonNormalizer;
use Spatie\LaravelData\Normalizers\ModelNormalizer;
use Spatie\LaravelData\Normalizers\ObjectNormalizer;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;

abstract class Data extends \Spatie\LaravelData\Data implements DtoDataInterface
{

//    private array $appendsToResult = [];
//
//    public function append(string $key, mixed $value): static
//    {
//        Arr::set($this->appendsToResult, $key, $value);
//        return $this;
//    }
//    public function getResultAppends(): array
//    {
//        return $this->appendsToResult;
//    }

    public function transform(null|TransformationContextFactory|TransformationContext $transformationContext = null): array
    {
        return parent::transform($transformationContext);
    }
//    public static function from(...$payloЯads): static
//    {
//        dump(static::class);
//        return parent::from(...$payloads);
//    }
//
//    public static function validateAndCreate(...$payloads): static
//    {
//        $dto = parent::from(...$payloads);
//        self::resolveRequestParams($dto, ...$payloads);
//        dump($payloads);
//        return $dto ;
//    }
//
//
//    public static function resolveRequestParams(Data $target, array $data): void
//    {
//        $reflection = new \ReflectionClass($target::class);
//
//
//        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
//            $attributes = self::getAttributes($property);
//            dump($property->getName());
//            $append = $attributes->contains(
//                fn(object $attribute) => $attribute instanceof Append
//            );
//
//            if ($append) dd($append);
//        }
//
//        return;
//    }
//
//    private static function getAttributes(ReflectionProperty $reflectionProperty): Collection
//    {
//        return collect($reflectionProperty->getAttributes())
//            ->filter(fn(\ReflectionAttribute $attribute) => class_exists($attribute->getName()))
//            ->map(fn(\ReflectionAttribute $attribute) => $attribute->newInstance());
//    }
//
//    public static function prepareForPipeline(array $properties): array
//    {
//        //dd(456745686);//3
//        $properties['metadata'] = \Arr::only($properties, ['release_year', 'producer']);
//
//        return $properties;
//    }
//
//    public static function pipeline(): DataPipeline
//    {
//        //dd(123452346345345);    //1
//        return DataPipeline::create()
//            ->into(static::class)
//            ->through(AuthorizedDataPipe::class)
//            ->through(MapPropertiesDataPipe::class)
//            ->through(FillRouteParameterPropertiesDataPipe::class)
//            ->through(ValidatePropertiesDataPipe::class)
//            ->through(DefaultValuesDataPipe::class)
//            ->through(CastPropertiesDataPipe::class);
//    }
//
//    public static function normalizers(): array
//    {
//        //dd(56756767); //2
//        return [
//            ModelNormalizer::class,
//            ArrayableNormalizer::class,
//            ObjectNormalizer::class,
//            ArrayNormalizer::class,
//            JsonNormalizer::class,
//        ];
//    }
}
