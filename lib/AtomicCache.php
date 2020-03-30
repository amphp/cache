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
     * Attempts to get the value for the given key. If the value is not found, the key is locked, the $create callback
     * is invoked with the key as the first parameter and null as the second parameter. The value returned from the
     * callback is stored in the cache and the promise returned from this method is resolved with the value.
     *
     * @param string   $key
     * @param callable(string $key, null $value): mixed $create
     * @param int|null $ttl
     *
     * @return Promise<mixed>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function load(string $key, callable $create, ?int $ttl = null): Promise
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
     * The key is locked, the current value in the cache is accessed, then the $create callback is invoked with the key
     * as the first parameter and the current value as the second parameter. The value returned from the callback is
     * stored in cache and the promise returned from this method is resolved with the value.
     *
     * @param string   $key
     * @param callable(string $key, mixed $value): mixed $modify
     * @param int|null $ttl
     *
     * @return Promise<mixed>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     * @throws SerializationException If serializing the value returned from the callback fails.
     */
    public function swap(string $key, callable $modify, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $modify, $ttl): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            $value = yield $this->cache->get($key);

            try {
                return yield from $this->create($modify, $key, $value, $ttl);
            } finally {
                $lock->release();
            }
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
     * @param $key string Cache key.
     *
     * @return Promise<mixed|null>
     *
     * @throws CacheException
     * @throws SerializationException
     *
     * @see SerializedCache::get()
     */
    public function get(string $key): Promise
    {
        return $this->cache->get($key);
    }

    /**
     * The lock is obtained for the key before setting the value.
     *
     * @param $key   string Cache key.
     * @param $value mixed Value to cache.
     * @param $ttl   int Timeout in seconds. The default `null` $ttl value indicates no timeout. Values less than 0 MUST
     *               throw an \Error.
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
}
