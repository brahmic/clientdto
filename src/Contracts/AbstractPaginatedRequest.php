<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Enums\PaginatedStrategy;
use Brahmic\ClientDTO\Requests\ResponseResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;


abstract class AbstractPaginatedRequest extends Data
{
    /** @var string<PaginableInterface> */
    protected string $requestClass;

    protected ?PaginableInterface $clientRequest = null;


    private PaginatedStrategy $strategy = PaginatedStrategy::Pages;

    /**
     * Количество страниц
     * @var int|null
     */
    public ?int $pages = null;

    public ?int $from = null;

    private ?int $to = null;

    private ?int $count = null;

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

    abstract public function sendRequest();

    abstract public function getResponseClass(): string;

    abstract public function getResolved(): mixed;

    public function send(): ClientResponse|ClientResponseInterface
    {
        $this->statusCode = 200;

        $this->sendRequest();

        return new ResponseResolver()->executePageable($this);
    }


    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    protected function makeRequest(): PaginableInterface|ClientRequestInterface
    {
        return $this->requestClass::from($this);
    }

    protected function preflightRequest(): void
    {
        if (method_exists($this, 'preflight') && $this->index = 1) {
            if ($resolved = $this->clientRequest->send()->resolved()) {
                $this->preflight($resolved);
            }
        }
    }

    public function fetchData(): Collection
    {
        $this->index = 1;


        if ($this->strategy === PaginatedStrategy::Range) {

            $this->preflightRequest();

            if (is_null($this->totalItems)) {
                throw new \Exception("Can't do range request. Use `preflight` method for set `totalItems`");
            }

            if ($this->to > $this->totalPages) {
                //$this->to =$this->from  + $this->totalPages;
                //throw new \Exception("Pages out of range. Maximum: `{$this->totalPages}` pages of `{$this->rows}` rows.");
            }

//dd(range($this->from, $this->to));
            return collect(range($this->from, $this->to))
                ->mapWithKeys(function ($page) {

                    if (!is_null($this->totalPages) && $page > $this->totalPages) {
                        return [$page => null];
                    }

                    if ($result = $this->clientRequest->setPage($page)->send()->resolved()) {
                        if (method_exists($this, 'firstResultCallback') && $this->index = 1) {
                            $this->firstResultCallback($result);
                        }
                    }

                    $this->index++;
                    return [$page => $result ?? false];
                });

        }


        dd(123);


        //$this->preflightRequest();

        //$this->clientRequest = $this->makeRequest();

        return collect(range($this->from, $this->pages))
            ->mapWithKeys(function ($page) {

                if (!is_null($this->totalPages) && $page > $this->totalPages) {
                    return [$page => null];
                }

                if ($result = $this->clientRequest->setPage($page)->send()->resolved()) {
                    if (method_exists($this, 'firstResultCallback') && $this->index = 1) {
                        $this->firstResultCallback($result);
                    }
                }

                $this->index++;
                return [$page => $result ?? false];
            });
    }


    public function mergeData(Collection $result): Collection
    {
        return $result->map(function ($item) {
            if (method_exists($this, 'mergeDataHandler')) {
                return $this->mergeDataHandler($item);
            }
            return $item;
        })->flatten(1);
    }

    public function whole(): void //todo rename to all
    {
        //todo need preflight
        $this->from = 1;
        $this->strategy = PaginatedStrategy::All;
    }

    public function pages(int $pages, ?int $rows = null): static
    {
        $this->from = 1;

        $this->pages = $pages;

        if ($rows) {
            $this->rows = $rows;
        }

        $this->strategy = PaginatedStrategy::Pages;

        return $this;
    }

    public function range(int $from, int $to, ?int $rows = null): static
    {
        $this->from = $from;

        $this->to = $to;

        if ($rows) {
            $this->rows = $rows;
        }

        $this->strategy = PaginatedStrategy::Range;

        return $this;
    }

    public function count(int $count): static
    {
        //todo need preflight or corrective
        $this->count = $count;

        $this->strategy = PaginatedStrategy::Count;

        return $this;
    }

    public function setRows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    protected function setTotalItems(?int $totalItems): void
    {
        $this->totalItems = $totalItems;
    }

    protected function setTotalPages(?int $totalPages): void
    {
        $this->totalPages = $totalPages;
    }

}
