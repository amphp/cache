<?php

namespace Amp\Cache;

use Amp\Reactor;
use Amp\Success;
use Amp\Failure;

class ArrayCache implements Cache {
    private $sharedState;
    private $ttlWatcherId;

    /**
     * By using a rebound TTL watcher with a shared state object we're
     * able to use __destruct() for "normal" garbage collection of
     * both this instance and the reactor watcher callback. Otherwise
     * this object could only be GC'd when the TTL watcher was cancelled
     * at the event reactor layer.
     */
    public function __construct() {
        $this->sharedState = $sharedState = new \StdClass;
        $sharedState->now = \time();
        $sharedState->cache = [];
        $sharedState->cacheTimeouts = [];
        $ttlWatcher = function ($watcherId) {
            // xdebug doesn't seem to generate code coverage
            // for this closure ... it's annoying.
            // @codeCoverageIgnoreStart
            $this->now = $now = \time();
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
        $this->ttlWatcherId = \Amp\repeat($ttlWatcher, 1000, $options = [
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
        $exists = isset($this->sharedState->cache[$key]) || array_key_exists($key, $this->sharedState->cache);

        return new Success($exists);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        return (isset($this->sharedState->cache[$key]) || array_key_exists($key, $this->sharedState->cache))
            ? new Success($this->sharedState->cache[$key])
            : new Failure(new \DomainException(
                "No cache entry exists at key \"{$key}\""
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null) {
        if (!isset($ttl)) {
            unset($this->sharedState->cacheTimeouts[$key]);
        } elseif (is_int($ttl) && $ttl >= 0) {
            $expiry = $this->sharedState->now + $ttl;
            $this->sharedState->cacheTimeouts[$key] = $expiry;
        } else {
            return new Failure(new \DomainException(
                "Invalid cache TTL; integer >= 0 or null required"
            ));
        }

        $this->sharedState->cache[$key] = $value;

        return new Success;
    }

    /**
     * {@inheritdoc}
     */
    public function del($key) {
        $exists = isset($this->sharedState->cache[$key]);
        if ($exists) {
            unset(
                $this->sharedState->cache[$key],
                $this->sharedState->cacheTimeouts[$key]
            );
        }

        return new Success($exists);
    }
}
