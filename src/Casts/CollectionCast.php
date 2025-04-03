<?php

namespace Brahmic\ClientDTO\Casts;

use Brahmic\ClientDTO\Support\Data;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class CollectionCast implements Cast
{
    public function __construct(private ?string $class = null)
    {
    }

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Collection
    {
        /** @var Data $class */
        if (!$class = $this->class) {
            if (method_exists($context->dataClass, 'collectionCast')) {

                if (is_array($class = $context->dataClass::collectionCast())) {
                    if (isset($class[$property->name])) {
                        $class = $class[$property->name];
                    } else {
                        throw new \Exception("The property name for the collection is incorrectly specified in the method or property collectionCast in the class {$context->dataClass}, expected key `$property->name`.");
                    }
                }

            }
//            else {
//                throw new \Exception("In class `{$context->dataClass}` You need to specify the DTO class for collection items in the WithCast attribute after CollectionCast as the second argument, or add a static method collectionCast that returns an array with casts for the property `{$property->name}`.");
//            }
        }

        return collect($value)->map(function ($item) use ($class) {
            if ($class) {

                $item = is_array($item) ? $item : ['value' => $item];
                $item = $this->handle($class, $item);
                return $class::validateAndCreate($item);
            }
            return $item;
        });
    }

    private function handle(string $class, mixed $data): mixed
    {
        return method_exists($class, 'handle') ? $class::handle($data) : $data;
    }

}
