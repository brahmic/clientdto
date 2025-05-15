<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\ClientDTO;
use Brahmic\ClientDTO\ResourceScanner\Context;
use Brahmic\ClientDTO\Traits\QueryParams;
use Illuminate\Support\Collection;

abstract class AbstractResource implements ChainInterface
{
    use QueryParams;

    public const ?string NAME = null; // Human-readable title of resource

    private ?ClientDTO $clientDTO = null;


    public static function getName(): ?string
    {
        return static::NAME;
    }


    public static function makeKey(Collection $chain): string
    {
        return $chain
            ->map(fn($class) => lcfirst(class_basename($class)))
            ->implode('-');
    }

    public static function declare(Context $context): array
    {
        return [
            'name' => static::getName(),
            'key' => static::makeKey($context->chain),
        ];
    }


//    public static function getResourceMap():ResourceMap
//    {
//        return ClientResolver::getInstance()->determineResourceMap(static::class);
//    }
}
