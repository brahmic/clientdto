<?php

namespace Brahmic\ClientDTO\Test\Provider;

use Spatie\LaravelData\Data;

/**
 * Базовый ответ, ожидаемый от сервера
 */
class ResponseDTO extends Data
{
    public function __construct(
        public mixed $response = null,
        public ?int  $waitTime = null,
        public ?int  $status = null,
    )
    {
    }
}

