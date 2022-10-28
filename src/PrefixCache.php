<?php

namespace Amp\Cache;

final class PrefixCache implements Cache
{
    public function __construct(
        private readonly Cache $cache,
        private readonly string $keyPrefix,
    ) {
    }

    /**
     * Gets the specified key prefix.
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
