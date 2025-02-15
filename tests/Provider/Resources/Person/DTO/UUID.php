<?php

namespace Brahmic\ClientDTO\Test\Provider\Resources\Person\DTO;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

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
