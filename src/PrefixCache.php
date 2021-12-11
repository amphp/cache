<?php

namespace Amp\Cache;

final class PrefixCache implements Cache
{
    private Cache $cache;
    private string $keyPrefix;

    public function __construct(Cache $cache, string $keyPrefix)
    {
        $this->cache = $cache;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Gets the specified key prefix.
     *
     * @return string
     */
    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function get(string $key): ?string
    {
        return $this->cache->get($this->keyPrefix . $key);
    }

    public function set(string $key, mixed $value, int $ttl = null): void
    {
        $this->cache->set($this->keyPrefix . $key, $value, $ttl);
    }

    public function delete(string $key): ?bool
    {
        return $this->cache->delete($this->keyPrefix . $key);
    }
}
