<?php

namespace Brahmic\ClientDTO\Contracts;

interface ResolvedHandlerInterface
{
    /**
     * Обрабатывает resolved данные после создания DTO
     *
     * @param mixed $dto Созданный DTO объект
     * @param AbstractRequest $request Объект запроса для доступа к параметрам
     * @return void
     */
    public function handle(mixed $dto, AbstractRequest $request): void;
}
