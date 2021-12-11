<?php

namespace Amp\Cache;

final class StringCacheAdapter implements StringCache
{
    private Cache $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function get(string $key): ?string
    {
        $value = $this->cache->get($key);

        if ($value !== null && !\is_string($value)) {
            throw new CacheException(
                'Received unexpected type from ' . \get_class($this->cache) . ': ' . \get_debug_type($value)
            );
        }

        return $value;
    }

    public function set(string $key, string $value, int $ttl = null): void
    {
        $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): ?bool
    {
        return $this->cache->delete($key);
    }
}
