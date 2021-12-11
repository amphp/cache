<?php

namespace Amp\Cache;

use Revolt\EventLoop;

final class LocalCache implements Cache
{
    private object $state;

    private string $gcCallbackId;

    private ?int $sizeLimit;

    /**
     * @param float $gcInterval The frequency in seconds at which expired cache entries should be garbage collected.
     * @param int|null $sizeLimit The maximum size of cache array (number of elements). NULL for unlimited size.
     */
    public function __construct(float $gcInterval = 5, int $sizeLimit = null)
    {
        // By using a separate state object we're able to use `__destruct()` for garbage collection of both this
        // instance and the event loop callback. Otherwise, this object could only be collected when the garbage
        // collection callback was cancelled at the event loop layer.
        $this->state = $state = new class {
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

        $this->gcCallbackId = EventLoop::repeat($gcInterval, \Closure::fromCallable([$state, "collectGarbage"]));
        $this->sizeLimit = $sizeLimit;

        EventLoop::unreference($this->gcCallbackId);
    }

    public function __destruct()
    {
        $this->state->cache = [];
        $this->state->cacheTimeouts = [];

        EventLoop::cancel($this->gcCallbackId);
    }

    public function get(string $key): ?string
    {
        if (!isset($this->state->cache[$key])) {
            return null;
        }

        if (isset($this->state->cacheTimeouts[$key]) && \time() > $this->state->cacheTimeouts[$key]) {
            unset(
                $this->state->cache[$key],
                $this->state->cacheTimeouts[$key]
            );

            return null;
        }

        return $this->state->cache[$key];
    }

    public function set(string $key, string $value, int $ttl = null): void
    {
        if ($ttl === null) {
            unset($this->state->cacheTimeouts[$key]);
        } elseif ($ttl >= 0) {
            $expiry = \time() + $ttl;
            $this->state->cacheTimeouts[$key] = $expiry;
            $this->state->isSortNeeded = true;
        } else {
            throw new \Error("Invalid cache TTL ({$ttl}; integer >= 0 or null required");
        }

        unset($this->state->cache[$key]);
        if (\count($this->state->cache) === $this->sizeLimit) {
            \array_shift($this->state->cache);
        }

        $this->state->cache[$key] = $value;
    }

    public function delete(string $key): bool
    {
        $exists = isset($this->state->cache[$key]);

        unset(
            $this->state->cache[$key],
            $this->state->cacheTimeouts[$key]
        );

        return $exists;
    }
}
