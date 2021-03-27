<?php

namespace Amp\Cache;

use Amp\Promise;
use Amp\Serialization\SerializationException;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Lock;

/**
 * @template TValue
 */
final class AtomicCache
{
    /** @var SerializedCache<TValue> */
    private SerializedCache $cache;

    private KeyedMutex $mutex;

    /**
     * @param SerializedCache<TValue> $cache
     * @param KeyedMutex              $mutex
     */
    public function __construct(SerializedCache $cache, KeyedMutex $mutex)
    {
        $this->cache = $cache;
        $this->mutex = $mutex;
    }

    /**
     * Obtains the lock for the given key, then invokes the $create callback with the current cached value (which may
     * be null if the key did not exist in the cache). The value returned from the callback is stored in the cache and
     * the promise returned from this method is resolved with the value.
     *
     * @param string   $key
     * @param callable(string, mixed|null): mixed $create Receives $key and $value as parameters.
     * @param int|null $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return mixed
     *
     * @psalm-param callable(string, TValue|null):(TValue|Promise<TValue>|\Generator<mixed, mixed, mixed, TValue>)
     *     $create
     * @psalm-return Promise<TValue>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function compute(string $key, callable $create, ?int $ttl = null): mixed
    {
        $lock = $this->lock($key);

        try {
            $value = $this->cache->get($key);

            return $this->create($create, $key, $value, $ttl);
        } finally {
            $lock->release();
        }
    }

    /**
     * Attempts to get the value for the given key. If the key is not found, the key is locked, the $create callback
     * is invoked with the key as the first parameter. The value returned from the callback is stored in the cache and
     * the promise returned from this method is resolved with the value.
     *
     * @param string   $key Cache key.
     * @param callable(string): mixed $create Receives $key as parameter.
     * @param int|null $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return mixed
     *
     * @psalm-param callable(string, TValue|null):(TValue|Promise<TValue>|\Generator<mixed, mixed, mixed, TValue>)
     *     $create
     * @psalm-return Promise<TValue>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function computeIfAbsent(string $key, callable $create, ?int $ttl = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value !== null) {
            return $value;
        }

        $lock = $this->lock($key);

        try {
            // Attempt to get the value again, since it may have been set while obtaining the lock.
            $value = $this->cache->get($key);

            if ($value !== null) {
                return $value;
            }

            return $this->create($create, $key, null, $ttl);
        } finally {
            $lock->release();
        }
    }

    /**
     * Attempts to get the value for the given key. If the key exists, the key is locked, the $create callback
     * is invoked with the key as the first parameter and the current key value as the second parameter. The value
     * returned from the callback is stored in the cache and the promise returned from this method is resolved with
     * the value.
     *
     * @param string   $key Cache key.
     * @param callable(string, mixed): mixed $create Receives $key and $value as parameters.
     * @param int|null $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return mixed
     *
     * @psalm-param callable(string, TValue|null): (TValue|Promise<TValue>|\Generator<mixed, mixed, mixed, TValue>)
     *     $create
     * @psalm-return Promise<TValue>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function computeIfPresent(string $key, callable $create, ?int $ttl = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            return null;
        }

        $lock = $this->lock($key);

        try {
            // Attempt to get the value again, since it may have been set while obtaining the lock.
            $value = $this->cache->get($key);

            if ($value === null) {
                return null;
            }

            return $this->create($create, $key, $value, $ttl);
        } finally {
            $lock->release();
        }
    }

    /**
     * The lock is obtained for the key before setting the value.
     *
     * @param string       $key Cache key.
     * @param mixed        $value Value to cache.
     * @param int|null     $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<void> Resolves either successfully or fails with a CacheException on failure.
     *
     * @psalm-param TValue $value
     * @psalm-return Promise<null>
     *
     * @throws CacheException
     * @throws SerializationException
     *
     * @see SerializedCache::set()
     */
    public function set(string $key, $value, ?int $ttl = null): void
    {
        $lock = $this->lock($key);

        try {
            $this->cache->set($key, $value, $ttl);
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns the cached value for the key or the given default value if the key does not exist.
     *
     * @template TDefault
     *
     * @param string         $key Cache key.
     * @param mixed          $default Default value returned if the key does not exist. Null by default.
     *
     * @return mixed Resolved with null iff $default is null.
     *
     * @psalm-param TDefault $default
     * @psalm-return Promise<TValue|TDefault>
     *
     * @throws CacheException
     * @throws SerializationException
     *
     * @see SerializedCache::get()
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value === null) {
            return $default;
        }

        return $value;
    }

    /**
     * The lock is obtained for the key before deleting the key.
     *
     * @param string $key
     *
     * @return bool|null
     *
     * @see SerializedCache::delete()
     */
    public function delete(string $key): ?bool
    {
        $lock = $this->lock($key);

        try {
            return $this->cache->delete($key);
        } finally {
            $lock->release();
        }
    }

    private function lock(string $key): Lock
    {
        try {
            return $this->mutex->acquire($key);
        } catch (\Throwable $exception) {
            throw new CacheException(
                \sprintf('Exception thrown when obtaining the lock for key "%s"', $key),
                0,
                $exception
            );
        }
    }

    /**
     * @psalm-param TValue|null $value
     */
    private function create(callable $create, string $key, mixed $value, ?int $ttl): mixed
    {
        try {
            $value = $create($key, $value);
        } catch (\Throwable $exception) {
            throw new CacheException(
                \sprintf('Exception thrown while creating the value for key "%s"', $key),
                0,
                $exception
            );
        }

        $this->cache->set($key, $value, $ttl);

        return $value;
    }
}
