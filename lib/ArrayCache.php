<?php

namespace Amp\Cache;

use Amp\Success;
use InvalidArgumentException;

class ArrayCache implements Cache {
    private $sharedState;
    private $ttlWatcherId;

    /**
     * By using a rebound TTL watcher with a shared state object we're
     * able to use __destruct() for "normal" garbage collection of
     * both this instance and the reactor watcher callback. Otherwise
     * this object could only be GC'd when the TTL watcher was cancelled
     * at the event reactor layer.
     *
     * @param int $gcInterval The frequency in milliseconds at which expired
     *                        cache entries should be garbage collected
     */
    public function __construct($gcInterval = 5000) {
        $this->sharedState = $sharedState = new \StdClass;
        $sharedState->cache = [];
        $sharedState->cacheTimeouts = [];
        $sharedState->isSortNeeded = false;
        $ttlWatcher = function () {
            // xdebug doesn't seem to generate code coverage
            // for this closure ... it's annoying.
            // @codeCoverageIgnoreStart
            $now = \time();
            if ($this->isSortNeeded) {
                \asort($this->cacheTimeouts);
                $this->isSortNeeded = false;
            }
            foreach ($this->cacheTimeouts as $key => $expiry) {
                if ($now > $expiry) {
                    unset(
                        $this->cache[$key],
                        $this->cacheTimeouts[$key]
                    );
                } else {
                    break;
                }
            }
            // @codeCoverageIgnoreEnd
        };
        $ttlWatcher = $ttlWatcher->bind($ttlWatcher, $sharedState);
        $this->ttlWatcherId = \Amp\repeat($ttlWatcher, $gcInterval, $options = [
            "enable" => true,
            "keep_alive" => false,
        ]);
    }

    public function __destruct() {
        $this->sharedState->cache = [];
        $this->sharedState->cacheTimeouts = [];
        \Amp\cancel($this->ttlWatcherId);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        if (!isset($this->sharedState->cache[$key])) {
            return new Success(null);
        }

        if (\time() > $this->sharedState->cacheTimeouts[$key]) {
            unset(
                $this->sharedState->cache[$key],
                $this->sharedState->cacheTimeouts[$key]
            );

            return new Success(null);
        }

        return new Success($this->sharedState->cache[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null) {
        if ($value === null) {
            throw new InvalidArgumentException("NULL is not allowed to be stored");
        }

        if ($ttl === null) {
            unset($this->sharedState->cacheTimeouts[$key]);
        } elseif (\is_int($ttl) && $ttl >= 0) {
            $expiry = \time() + $ttl;
            $this->sharedState->cacheTimeouts[$key] = $expiry;
            $this->sharedState->isSortNeeded = true;
        } else {
            throw new InvalidArgumentException("Invalid cache TTL; integer >= 0 or null required");
        }

        $this->sharedState->cache[$key] = $value;

        return new Success;
    }

    /**
     * {@inheritdoc}
     */
    public function del($key) {
        $exists = isset($this->sharedState->cache[$key]);

        unset(
            $this->sharedState->cache[$key],
            $this->sharedState->cacheTimeouts[$key]
        );

        return new Success($exists);
    }
}
