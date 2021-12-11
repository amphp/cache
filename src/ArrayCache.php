<?php

namespace Amp\Cache;

use Revolt\EventLoop;

final class ArrayCache implements Cache
{
    private object $sharedState;

    private string $ttlWatcherId;

    private ?int $maxSize;

    /**
     * @param float $gcInterval The frequency in seconds at which expired cache entries should be garbage collected.
     * @param int|null $maxSize The maximum size of cache array (number of elements). NULL for no max size.
     */
    public function __construct(float $gcInterval = 5, int $maxSize = null)
    {
        // By using a shared state object we're able to use `__destruct()` for "normal" garbage collection of both this
        // instance and the loop's watcher. Otherwise, this object could only be GC'd when the TTL watcher was cancelled
        // at the loop layer.
        $this->sharedState = $sharedState = new class {
            /** @var string[] */
            public array $cache = [];
            /** @var int[] */
            public array $cacheTimeouts = [];

            public bool $isSortNeeded = false;

            public function collectGarbage(): void
            {
                $now = \time();

                if ($this->isSortNeeded) {
                    \asort($this->cacheTimeouts);
                    $this->isSortNeeded = false;
                }

                foreach ($this->cacheTimeouts as $key => $expiry) {
                    if ($now <= $expiry) {
                        break;
                    }

                    unset(
                        $this->cache[$key],
                        $this->cacheTimeouts[$key]
                    );
                }
            }
        };

        $this->ttlWatcherId = EventLoop::repeat($gcInterval, \Closure::fromCallable([$sharedState, "collectGarbage"]));
        $this->maxSize = $maxSize;

        EventLoop::unreference($this->ttlWatcherId);
    }

    public function __destruct()
    {
        $this->sharedState->cache = [];
        $this->sharedState->cacheTimeouts = [];

        EventLoop::cancel($this->ttlWatcherId);
    }

    /** @inheritdoc */
    public function get(string $key): ?string
    {
        if (!isset($this->sharedState->cache[$key])) {
            return null;
        }

        if (isset($this->sharedState->cacheTimeouts[$key]) && \time() > $this->sharedState->cacheTimeouts[$key]) {
            unset(
                $this->sharedState->cache[$key],
                $this->sharedState->cacheTimeouts[$key]
            );

            return null;
        }

        return $this->sharedState->cache[$key];
    }

    /** @inheritdoc */
    public function set(string $key, string $value, int $ttl = null): void
    {
        if ($ttl === null) {
            unset($this->sharedState->cacheTimeouts[$key]);
        } elseif ($ttl >= 0) {
            $expiry = \time() + $ttl;
            $this->sharedState->cacheTimeouts[$key] = $expiry;
            $this->sharedState->isSortNeeded = true;
        } else {
            throw new \Error("Invalid cache TTL ({$ttl}; integer >= 0 or null required");
        }

        unset($this->sharedState->cache[$key]);
        if (\count($this->sharedState->cache) === $this->maxSize) {
            \array_shift($this->sharedState->cache);
        }

        $this->sharedState->cache[$key] = $value;
    }

    /** @inheritdoc */
    public function delete(string $key): bool
    {
        $exists = isset($this->sharedState->cache[$key]);

        unset(
            $this->sharedState->cache[$key],
            $this->sharedState->cacheTimeouts[$key]
        );

        return $exists;
    }
}
