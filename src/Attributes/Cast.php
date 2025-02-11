<?php

namespace Brahmic\ClientDTO\Attributes;

use Attribute;
use Brahmic\ClientDTO\Exceptions\CannotCreateCastAttribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Cast implements Castable
{

    public array $arguments;

    public function __construct(
        /** @var class-string<\Spatie\LaravelData\Casts\Cast> $castClass */
        public string $castClass,
        mixed ...$arguments
    ) {
        if (! is_a($this->castClass, Cast::class, true)) {
            throw CannotCreateCastAttribute::notACast();
        }

        $this->arguments = $arguments;
    }

    public function get(): Cast
    {
        return new ($this->castClass)(...$this->arguments);
    }
}
