<?php

namespace Brahmic\ClientDTO\Contracts;

interface PaginableInterface
{

    public function setPage(int $page): static;

    public function setRows(int $rows): static;

    public function previousPage(): mixed;

    public function nextPage(): mixed;

    public function getRows(): int;
}
