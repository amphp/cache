<?php

namespace Amp\Cache;

use Amp\Promise;

interface SerializedCache extends Cache
{
    /**
     * The promise is resolved with the unserialized cached value.
     *
     * @param $key string Cache key.
     *
     * @return Promise<mixed|null> Resolves to the cached value nor `null` if it doesn't exist or fails with a
     * CacheException on failure.
     */
    public function get(string $key): Promise;

    /**
     * Allows any serializable value to cached, not just strings.
     *
     * @param $key string Cache key.
     * @param $value mixed Value to cache.
     * @param $ttl int Timeout in seconds. The default `null` $ttl value indicates no timeout. Values less than 0 MUST
     * throw an \Error.
     *
     * @return Promise<void> Resolves either successfully or fails with a CacheException on failure.
     */
    public function set(string $key, $value, int $ttl = null): Promise;
}
