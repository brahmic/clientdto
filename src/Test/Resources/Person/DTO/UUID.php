<?php

namespace Brahmic\ClientDTO\Test\Resources\Person\DTO;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;

/**
 * Идентификатор проверяемой сущности
 */
class UUID extends Data
{
    public function __construct(
        public int $status,
        #[MapInputName('query_type')] // поле query_type из JSON должно быть сопоставлено с queryType
        public int $queryType,
        public string $uuid,
    ) {
    }
}
