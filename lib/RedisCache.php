<?php

namespace Amp\Cache;

use Amp\Redis\Client;

class RedisCache extends PrefixCache {
    /** @var Client */
    private $client;

    /**
     * {@inheritdoc}
     */
    public function __construct(Client $client, $keyPrefix = "") {
        parent::__construct($keyPrefix);
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key) {
        return $this->client->exists($this->keyPrefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        return $this->client->get($this->keyPrefix . $key);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 0) {
        return $this->client->set($this->keyPrefix . $key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function del($key) {
        return $this->client->del($this->keyPrefix . $key);
    }
}
