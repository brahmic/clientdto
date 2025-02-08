<?php

namespace Brahmic\ClientDTO\Test\Resources\Person\Support;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\Validation\Distinct;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

final class PersonalData extends Data
{

    public function __construct(
        #[Required]
        #[Min(1)] // Минимум 1 регион
        #[Max(2)] // Максимум 2 региона
        #[Distinct] // Все элементы массива должны быть уникальным
        public readonly array $regions,
        #[Required]
        public readonly string  $lastName,
        #[Required]
        public readonly string  $firstName,

        public readonly ?string $secondName = null,
        public readonly ?Carbon $birthDate = null,
        #[Max(4)]
        public readonly ?string $passportSerial = null,
        #[Max(6)]
        public readonly ?string $passportNumber = null,
        #[Max(12)]
        public readonly ?string $inn = null,
    )
    {
    }
}
