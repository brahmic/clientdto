<?php

namespace Brahmic\ClientDTO\Casts;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class StringedArray implements Transformer
{
    private function arrayToString($array, bool $trim = false): string
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[] = is_array($value) ? $this->arrayToString($value) : $value;
        }

        return '[' . implode($trim ? ',' : ', ', $result) . ']';
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return $this->arrayToString($value, true);
    }
}