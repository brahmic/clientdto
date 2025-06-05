<?php

namespace Brahmic\ClientDTO\Response;

use Arr;
use Illuminate\Contracts\Support\Arrayable;

class ClientResult implements Arrayable
{

    protected mixed $data = null;
    protected array $appends = [];

    public function __construct(mixed $data = null)
    {
        $this->fill($data);
    }

    public function data(): ?array
    {
        return $this->data;
    }

    public function clear(): static
    {
        $this->data = [];

        return $this;
    }

    public function fill(null|array|string|object $data = null): void
    {
        if (is_array($data) || is_string($data)) {
            $this->data = $data;
        } else {
            if ($data) {
                if ($data instanceof Arrayable) {
                    $this->data = $data->toArray();

                } else {
                    $this->data = ['object'];
                }
            }
        }
    }

    private function canArray(): void
    {
        if (!is_null($this->data) && !is_array($this->data)) {
            throw new \RuntimeException('Cannot use $data for array operations');
        }
    }

    public function add(mixed $value, ?bool $condition = true): static
    {
        $this->canArray();

        if ($condition) {
            $this->data[] = $value;
        }

        return $this;
    }

    public function set(string $key, mixed $value, ?bool $condition = true): static
    {
        $this->canArray();

        if ($condition) {
            Arr::set($this->data, $key, $value);
        }

        return $this;
    }
    public function get(string $key, ?bool $default = null): mixed
    {
        $this->canArray();

        return Arr::get($this->data, $key, $default);
    }

    public function prepend(string $key, mixed $value): static
    {
        $this->canArray();

        $this->data = [$key => $value] + $this->data;

        return $this;
    }


    public function merge(array $items): static
    {
        $this->canArray();

        $this->data = array_merge($this->data, $items);

        return $this;
    }

    public function except(array|string $keys): static
    {
        $this->canArray();

        $this->data = Arr::except($this->data, $keys);

        return $this;
    }

    public function toArray(): array
    {
        return ($this->data instanceof Arrayable)
            ? $this->data->toArray()
            : $this->data;
    }
}
