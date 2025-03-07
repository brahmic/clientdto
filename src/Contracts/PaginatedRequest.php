<?php

namespace Brahmic\ClientDTO\Contracts;

use Brahmic\ClientDTO\Enums\PaginatedStrategy;
use Brahmic\ClientDTO\Exceptions\PaginationRequestException;
use Brahmic\ClientDTO\Exceptions\PreflightRequestException;
use Brahmic\ClientDTO\Requests\ResponseResolver;
use Brahmic\ClientDTO\Response\ClientResponse;
use Exception;
use Illuminate\Support\Collection;


class PaginatedRequest
{

    /**
     * Number of pages (it means from the first page)
     * @var int|null
     */
    private ?int $pages = null;

    /**
     * From page
     * @var int|null
     */
    private ?int $from = null;

    /**
     * To page
     * @var int|null
     */
    private ?int $to = null;

    /**
     * Number of records (it means from the first page)
     * @var int|null
     */
    private ?int $number = null;

    /**
     * Rows per page
     * @var int
     */
    private int $rows = 20;

    private int $loop;

    private ?int $totalPages = null;

    private ?int $totalItems = null;

    private ?int $attempt = null;

    private ?int $statusCode = null;

    private ?Collection $result = null;

    private ?Collection $failed = null;

    /** @var string<PaginableRequestInterface> */
    private string $requestClass;

    private AbstractRequest $clientRequest;

    private PaginatedStrategy $strategy = PaginatedStrategy::Pages;


    public function __construct(AbstractRequest $request)
    {
        if (!$request instanceof PaginableRequestInterface) {
            throw new Exception('Request should be implement PaginableRequestInterface');
        }

        $this->clientRequest = $request;
    }


    public function send(): ClientResponse|ClientResponseInterface
    {
        $this->statusCode = 200;

        $this->result = $this->fetchData();

        return new ResponseResolver()->executePageable($this);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getClientRequest(): AbstractRequest
    {
        return $this->clientRequest;
    }

    public function getResolved(): ?array
    {
        if ($this->result) {
            return [
                'total' => $this->totalItems,
                'items' => $this->result,
            ];
        }

        return null;
    }


    private function preflightRequest(): void
    {
        if (method_exists($this->clientRequest, 'preflight') && $this->loop = 1) {

            $clone = clone($this->clientRequest);

            if ($resolved = $clone->firstPage()) {

                $this->clientRequest->preflight($this, $resolved);

                //return;
            } else {
                //dd($clone->getResponse()->toArray());
                throw new PreflightRequestException("Preflight request error. {$clone->getResponse()->getMessage()}.");
            }
        }

        if (is_null($this->totalItems) || is_null($this->totalPages)) {
            throw new PreflightRequestException("Can't complete the paginated request. Use `preflight` method for set `totalItems` and `totalPages`");
        }

    }

    private function fetchData(): ?Collection
    {
        $this->failed = collect();

        $this->attempt = 0;


        try {
            $this->preflightRequest();

            $data = match ($this->strategy) {
                PaginatedStrategy::Pages => $this->strategyPages(),
                PaginatedStrategy::Range => $this->strategyRange(),
                PaginatedStrategy::Number => $this->strategyNumber(),
                PaginatedStrategy::All => $this->strategyAll(),
            };

            return $this->mergeResults($data);

        } catch (PaginationRequestException $exception) {

            //throw $exception;
            return null;
        }
    }

    private function mergeResults(Collection $result): Collection
    {
        return $result->map(function ($item) {
            if (method_exists($this->clientRequest, 'mergeDataHandler')) {
                return $this->clientRequest->mergeDataHandler($item);
            }
            return $item;
        })->flatten(1);
    }

    /**
     * @throws PaginationRequestException
     */
    private function fetch(?Collection $pages = null): Collection
    {
        $this->loop = 0;

        $this->clientRequest->setRows($this->rows);

        $result = collect($pages ?? range($this->from, $this->to))
            ->mapWithKeys(function ($page) {

                if (!is_null($this->totalPages) && $page > $this->totalPages) {
                    throw new \RuntimeException('Incorrect total record and page count');
                    //return [$page => null];
                }

                $clientResponse = $this->clientRequest->setPage($page)->send();

                if ($result = $clientResponse->resolved()) {
                    if (method_exists($this, 'afterFirstResult') && $this->loop === 0) {
                        $this->afterFirstResult($result);
                    }
                } else {
                    $this->failed->put($page, $clientResponse->toArray());
                }

                $this->loop++;

                //return [$page => false];
                return [$page => $result ?? false];
            });

        return $this->retryFailed($result);
    }

    private function retryFailed(Collection $result): Collection
    {
        $brokenPages = $result->filter(fn($value) => $value === false);

        if ($brokenPages->isEmpty()) {

            return $result;

        } elseif ($this->attempt < 3) { /* max attempts */

            $this->attempt++;
            return $this->fetch($brokenPages->keys());
        }

        throw new PaginationRequestException('Failed to retrieve all required pages', $this->failed);
    }

    /**
     * @throws PaginationRequestException
     */
    private function strategyNumber(): Collection
    {
        if ($this->rows > $this->number) {
            $this->rows = $this->number;
        }

        $this->to = (int)floor($this->number / $this->rows);

        $result = $this->fetch();

        if ($this->to > $this->from) {
            $this->rows = $this->number - (($this->to - $this->from + 1) * $this->rows);
            $this->from = $this->to + 1;
            $this->to = $this->from;
            $result = $result->merge($this->fetch());
        }

        return $result;
    }

    /**
     * @throws PaginationRequestException
     */
    private function strategyAll(): Collection
    {
        $this->to = ceil($this->totalItems / $this->rows);

        return $this->fetch();
    }

    /**
     * @throws PaginationRequestException
     */
    private function strategyPages(): Collection
    {
        $this->to = $this->pages > $this->totalPages ? $this->totalPages : $this->pages;

        return $this->fetch();
    }

    /**
     * @throws \Exception
     */
    private function strategyRange(): Collection
    {
        if ($this->from > $this->totalPages) {
            throw new \Exception("Pages `from` out of range. Maximum: `{$this->totalPages}` pages of `{$this->rows}` rows.");
        }

        if ($this->to > $this->totalPages) {
            $this->to = $this->totalPages;
            //throw new \Exception("Pages `to` out of range. Maximum: `{$this->totalPages}` pages of `{$this->rows}` rows.");
        }

        return $this->fetch();
    }


    public function all(?int $rows = null): static //todo rename to all
    {
        $this->from = 1;

        if ($rows) {
            $this->rows = $rows;
        }

        $this->strategy = PaginatedStrategy::All;

        return $this;
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

    public function number(int $number): static
    {
        $this->from = 1;

        $this->number = $number;

        $this->strategy = PaginatedStrategy::Number;

        return $this;
    }

    public function setRows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function setTotalItems(?int $totalItems): void
    {
        $this->totalItems = $totalItems;
    }

    public function setTotalPages(?int $totalPages): void
    {
        $this->totalPages = $totalPages;
    }
}
