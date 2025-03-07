<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Response\ClientResponse;

interface PaginableInterface
{

    public function setPage(int $page): static;

    public function setRows(int $rows): static;

    public function previousPage(): mixed;

    public function nextPage(): mixed;

    public function firstPage(): mixed;

    public function getRows(): int;

    //public function send(): ClientResponseInterface|ClientResponse;
}
