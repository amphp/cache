<?php

namespace Amp\Cache;

use Amp\Promise;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Lock;
use function Amp\call;

final class AtomicCache implements Cache
{
    /** @var Cache */
    private $cache;

    /** @var KeyedMutex */
    private $mutex;

    public function __construct(Cache $cache, KeyedMutex $mutex)
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
     * @param callable(string $key) $create
     * @param int|null $ttl
     *
     * @return Promise<string>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
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
     * @param callable(string $key, mixed $value) $create
     * @param int|null $ttl
     *
     * @return Promise<string>
     *
     * @throws CacheException If the $create callback throws an exception while generating the value.
     */
    public function swap(string $key, callable $create, ?int $ttl = null): Promise
    {
        return call(function () use ($key, $create, $ttl): \Generator {
            $lock = yield from $this->lock($key);
            \assert($lock instanceof Lock);

            $value = yield $this->cache->get($key);

            try {
                return yield from $this->create($create, $key, $value, $ttl);
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

    private function create(callable $create, string $key, ?string $value, ?int $ttl): \Generator
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

        if (!\is_string($value)) {
            throw new CacheException(\sprintf(
                'The value to be cached for key "%s" must be a string, %s returned',
                $key,
                \is_object($value) ? \get_class($value) : \gettype($value)
            ));
        }

        yield $this->cache->set($key, $value, $ttl);

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): Promise
    {
        return $this->cache->get($key);
    }

    /**
     * The lock is obtained for the key before setting the value.
     *
     * @inheritDoc
     */
    public function set(string $key, string $value, ?int $ttl = null): Promise
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
     * @inheritDoc
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
