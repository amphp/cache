<?php

namespace Amp\Cache;

use Amp\Promise;

abstract class PrefixCache implements Cache {
    private $cache;
    private $keyPrefix;

    public function __construct(Cache $cache, $keyPrefix) {
        // @TODO Remove this check once PHP7 is required and use a scalar param type
        if (!\is_string($keyPrefix)) {
            throw new \InvalidArgumentException(\sprintf(
                "keyPrefix must be string, %s given",
                \gettype($keyPrefix)
            ));
        }

        $this->cache = $cache;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Gets the current prefix.
     *
     * @return string
     */
    public function getKeyPrefix(): string {
        return $this->keyPrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): Promise {
        return $this->cache->get($this->keyPrefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = null): Promise {
        return $this->cache->set($this->keyPrefix . $key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function del(string $key): Promise {
        return $this->cache->del($this->keyPrefix . $key);
    }
}
