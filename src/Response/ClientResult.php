<?php

namespace Brahmic\ClientDTO\Response;

use Arr;
use Illuminate\Contracts\Support\Arrayable;

class ClientResult implements Arrayable
{

    protected array $result = [];
    protected array $appends = [];

    public function __construct(null|array|object $data = null)
    {

        if (is_array($data)) {

            $this->result = $data;
        } else {
            if ($data) {
                if ($data instanceof Arrayable) {
                    $this->result = $data->toArray();

                } else {
                    $this->result = ['object'];
                }
            }
        }
    }

    public function set(string $key, mixed $value, ?bool $condition = true): static
    {
        if ($condition) {
            Arr::set($this->result, $key, $value);
        }

        return $this;
    }

    public function prepend(string $key, mixed $value): static
    {
        $this->result = [$key => $value] + $this->result;

        return $this;
    }


    public function merge(array $items): static
    {
        $this->result = array_merge($this->result, $items);

        return $this;
    }

    public function except(array|string $keys): static
    {
        $this->result = Arr::except($this->result, $keys);

        return $this;
    }

    public function toArray(): array
    {
        return $this->result;
    }
}
