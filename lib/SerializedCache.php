<?php

namespace Amp\Cache;

use Amp\Serialization\Serializer;

/**
 * @template TValue
 */
final class SerializedCache
{
    private Cache $cache;

    private Serializer $serializer;

    public function __construct(Cache $cache, Serializer $serializer)
    {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * Fetch a value from the cache and unserialize it.
     *
     * @param $key string Cache key.
     *
     * @return mixed Returns the cached value or `null` if it doesn't exist. Throws a CacheException or
     * SerializationException on failure.
     *
     * @psalm-return TValue
     *
     * @see Cache::get()
     */
    public function get(string $key): mixed
    {
        $data = $this->cache->get($key);
        if ($data === null) {
            return null;
        }

        return $this->serializer->unserialize($data);
    }

    /**
     * Serializes a value and stores its serialization to the cache.
     *
     * @param string       $key Cache key.
     * @param mixed        $value Value to cache.
     * @param int|null     $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout. Values less
     *     than 0 MUST throw an \Error.
     *
     * @psalm-param TValue $value
     *
     * @see Cache::set()
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if ($value === null) {
            throw new CacheException('Cannot store NULL in serialized cache');
        }

        $value = $this->serializer->serialize($value);

        $this->cache->set($key, $value, $ttl);
    }

    /**
     * Deletes a value associated with the given key if it exists.
     *
     * @param $key string Cache key.
     *
     * @return bool|null Returns `true` / `false` to indicate whether the key existed or fails with a
     * CacheException on failure. May also return `null` if that information is not available.
     *
     * @see Cache::delete()
     */
    public function delete(string $key): ?bool
    {
        return $this->cache->delete($key);
    }
}
