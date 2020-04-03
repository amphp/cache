<?php

namespace Amp\Cache;

use Amp\Promise;
use Amp\Serialization\SerializationException;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Lock;
use function Amp\call;

final class AtomicCache
{
    /** @var SerializedCache */
    private $cache;

    /** @var KeyedMutex */
    private $mutex;

    /**
     * @param SerializedCache $cache
     * @param KeyedMutex      $mutex
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
     * @param callable(string $key, mixed|null $value): mixed $create
     * @param int|null $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<mixed>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function compute(string $key, callable $create, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $create, $ttl): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                $value = yield $this->cache->get($key);

                return yield from $this->create($create, $key, $value, $ttl);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Attempts to get the value for the given key. If the key is not found, the key is locked, the $create callback
     * is invoked with the key as the first parameter and null as the second parameter. The value returned from the
     * callback is stored in the cache and the promise returned from this method is resolved with the value.
     *
     * @param string   $key Cache key.
     * @param callable(string $key, null $value): mixed $create
     * @param int|null $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<mixed>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function computeIfAbsent(string $key, callable $create, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $create, $ttl): \Generator {
            $value = yield $this->cache->get($key);

            if ($value !== null) {
                return $value;
            }

            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                // Attempt to get the value again, since it may have been set while obtaining the lock.
                $value = yield $this->cache->get($key);

                if ($value !== null) {
                    return $value;
                }

                return yield from $this->create($create, $key, null, $ttl);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Attempts to get the value for the given key. If the key exists, the key is locked, the $create callback
     * is invoked with the key as the first parameter and the current key value as the second parameter. The value
     * returned from the callback is stored in the cache and the promise returned from this method is resolved with
     * the value.
     *
     * @param string   $key Cache key.
     * @param callable(string $key, mixed $value): mixed $create
     * @param int|null $ttl Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<mixed>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function computeIfPresent(string $key, callable $create, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $create, $ttl): \Generator {
            $value = yield $this->cache->get($key);

            if ($value === null) {
                return null;
            }

            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                // Attempt to get the value again, since it may have been set while obtaining the lock.
                $value = yield $this->cache->get($key);

                if ($value === null) {
                    return null;
                }

                return yield from $this->create($create, $key, $value, $ttl);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * The lock is obtained for the key before setting the value.
     *
     * @param string   $key   Cache key.
     * @param mixed    $value Value to cache.
     * @param int|null $ttl   Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<void> Resolves either successfully or fails with a CacheException on failure.
     *
     * @throws CacheException
     * @throws SerializationException
     *
     * @see SerializedCache::set()
     */
    public function set(string $key, $value, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $value, $ttl): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                yield $this->cache->set($key, $value, $ttl);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * The lock is obtained for the key and the value is set only if the key does not exist.
     *
     * @param string   $key   Cache key.
     * @param mixed    $value Value to cache.
     * @param int|null $ttl   Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<mixed> Resolves either with the current value or with the newly set value. Fails with a
     * CacheException or SerializationException on failure.
     *
     * @throws CacheException
     * @throws SerializationException
     */
    public function setIfAbsent(string $key, $value, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $value, $ttl): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                $currentValue = yield $this->cache->get($key);

                if ($currentValue !== null) {
                    return $currentValue;
                }

                yield $this->cache->set($key, $value, $ttl);
            } finally {
                $lock->release();
            }

            return $value;
        });
    }

    /**
     * The lock is obtained for the key and the value is set only if the key already exists.
     *
     * @param string   $key   Cache key.
     * @param mixed    $value Value to cache.
     * @param int|null $ttl   Timeout in seconds. The default `null` $ttl value indicates no timeout.
     *
     * @return Promise<mixed|null> Resolves either with null if the key did not exist or with the newly set value.
     * Fails with a CacheException or SerializationException on failure.
     *
     * @throws CacheException
     * @throws SerializationException
     */
    public function setIfPresent(string $key, $value, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $value, $ttl): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                $currentValue = yield $this->cache->get($key);

                if ($currentValue === null) {
                    return null;
                }

                yield $this->cache->set($key, $value, $ttl);
            } finally {
                $lock->release();
            }

            return $value;
        });
    }

    private function lock(string $key): \Generator
    {
        try {
            return yield $this->mutex->acquire($key);
        } catch (\Throwable $exception) {
            throw new CacheException(
                \sprintf('Exception thrown when obtaining the lock for key "%s"', $key),
                0,
                $exception
            );
        }
    }

    private function create(callable $create, string $key, $value, ?int $ttl): \Generator
    {
        try {
            $value = yield call($create, $key, $value);
        } catch (\Throwable $exception) {
            throw new CacheException(
                \sprintf('Exception thrown while creating the value for key "%s"', $key),
                0,
                $exception
            );
        }

        yield $this->cache->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Returns the cached value for the key or the given default value if the key does not exist.
     *
     * @param string     $key     Cache key.
     * @param mixed|null $default Default value returned if the key does not exist. Null by default.
     *
     * @return Promise<mixed|null> Resolved with null iff $default is null.
     *
     * @throws CacheException
     * @throws SerializationException
     *
     * @see SerializedCache::get()
     */
    public function get(string $key, $default = null): Promise
    {
        return call(function () use ($key, $default): \Generator {
            $value = yield $this->cache->get($key);

            if ($value === null) {
                return $default;
            }

            return $value;
        });
    }

    /**
     * The lock is obtained for the key before deleting the key.
     *
     * @param string $key
     *
     * @return Promise<bool>
     *
     * @see SerializedCache::delete()
     */
    public function delete(string $key): Promise
    {
        return call(function () use ($key): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                return yield $this->cache->delete($key);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Deletes the cached value for the given key if the $check callback function returns a truthy value. If the cache
     * does not contain the given key, the callback is not invoked.
     *
     * @param string   $key
     * @param callable(string $key, mixed $value): bool $check
     *
     * @return Promise<bool> Resolves with true if the key was present and subsequently deleted from the cache or
     *                       false if the key was not deleted from the cache.
     */
    public function deleteIf(string $key, callable $check): Promise
    {
        return call(function () use ($key, $check): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            try {
                $value = yield $this->cache->get($key);

                if ($value === null) {
                    return false;
                }

                try {
                    $result = yield call($check, $key, $value);
                } catch (\Throwable $exception) {
                    throw new CacheException(
                        \sprintf('Exception thrown from delete callback for key "%s"', $key),
                        0,
                        $exception
                    );
                }

                if (!$result) {
                    return false;
                }

                return yield $this->cache->delete($key);
            } finally {
                $lock->release();
            }
        });
    }
}
