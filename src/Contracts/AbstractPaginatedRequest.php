<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Requests\ResponseResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Closure;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

abstract class AbstractPaginatedRequest extends Data
{
    protected ?AbstractRequest $clientRequest = null;

    /** @var string<PaginableInterface> */
    protected string $requestClass;

    /**
     * Количество страниц
     * @var int|null
     */
    public ?int $pages = null;

    public int $formPage = 1;

    /**
     * Кол-во строк на странице
     * @var int
     */
    public int $rows = 20;


    protected int $index;
    protected ?int $totalPages = null;
    protected ?int $totalItems = null;

    protected ?int $statusCode = null;

    protected ?Collection $collection = null;

    public function send(): ClientResponse|ClientResponseInterface
    {
        $this->statusCode = 200;

        $this->sendRequest();

        return new ResponseResolver()->executePageable($this);
    }

    abstract public function sendRequest();

    abstract public function getResponseClass(): string;

    abstract public function getResolved(): mixed;

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    protected function makeRequest(): PaginableInterface
    {
        return $this->requestClass::from($this);
    }

    public function asdfasdf($after = null): Collection
    {
        $clientRequest = $this->makeRequest();

        return collect(range($this->formPage, $this->pages))
            ->mapWithKeys(function ($page) use ($clientRequest, $after) {

                if (!is_null($this->totalPages) && $page > $this->totalPages) {
                    return [$page => null];
                }

                if ($result = $clientRequest->nextPage()) {
                    if ($after instanceof Closure && $this->index = 1) {
                        $after($result, $clientRequest->getRows());
                    }
                }

                $this->index++;
                return [$page => $result ?? false];
            });
    }


    public function prepareResult(Collection $result): Collection
    {
        return $result->map(function ($item) {
//            if ($item instanceof IrbisPageableDto) {
//                return $item->getItems();
//            }
            return $item;
        })->flatten(1);
    }

    public function setPages(int $pages): static
    {
        $this->pages = $pages;
        return $this;
    }

    public function formPage(int $from): static
    {
        $this->formPage = $from;
        return $this;
    }

    public function setRows(int $rows): static
    {
        $this->rows = $rows;
        return $this;
    }
}
