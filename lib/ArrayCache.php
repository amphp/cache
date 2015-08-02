<?php

namespace Amp\Cache;

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
        $ttlWatcher = function ($watcherId) {
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
    public function has($key) {
        if (!\array_key_exists($key, $this->sharedState->cache)) {
            $exists = false;
        } elseif (\time() > $this->sharedState->cacheTimeouts[$key]) {
            unset(
                $this->sharedState->cache[$key],
                $this->sharedState->cacheTimeouts[$key]
            );
            $exists = false;
        } else {
            $exists = true;
        }

        return new \Amp\Success($exists);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        return (isset($this->sharedState->cache[$key]) || array_key_exists($key, $this->sharedState->cache))
            ? new \Amp\Success($this->sharedState->cache[$key])
            : new \Amp\Failure(new \DomainException(
                "No cache entry exists at key \"{$key}\""
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null) {
        if (!isset($ttl)) {
            unset($this->sharedState->cacheTimeouts[$key]);
        } elseif (\is_int($ttl) && $ttl >= 0) {
            $expiry = \time() + $ttl;
            $this->sharedState->cacheTimeouts[$key] = $expiry;
            $this->sharedState->isSortNeeded = true;
        } else {
            return new \Amp\Failure(new \DomainException(
                "Invalid cache TTL; integer >= 0 or null required"
            ));
        }

        $this->sharedState->cache[$key] = $value;

        return new \Amp\Success;
    }

    /**
     * {@inheritdoc}
     */
    public function del($key) {
        unset(
            $this->sharedState->cache[$key],
            $this->sharedState->cacheTimeouts[$key]
        );

        return new \Amp\Success($exists);
    }
}
