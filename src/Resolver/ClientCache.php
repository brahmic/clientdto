<?php

namespace Brahmic\ClientDTO\Resolver;

use Brahmic\ClientDTO\ResourceScanner\ResourceMap;
use Cache;
use Illuminate\Support\Carbon;

class ClientCache
{
    public function get(string $key): ?ResourceMap
    {
        return Cache::get($key);
    }

    public function put(string $key, ResourceMap $resolvedClient): void
    {
        Cache::put($key, $resolvedClient, Carbon::now()->addMonth());
    }

    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    public function clear(string $key): void
    {
        Cache::forget($key);
    }
}
