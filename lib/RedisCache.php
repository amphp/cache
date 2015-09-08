<?php

namespace Amp\Cache;

use Amp\Redis\Client;

class RedisCache {
    /** @var Client */
    private $client;

    /**
     * {@inheritdoc}
     */
    public function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key) {
        return $this->client->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null) {
        return $this->client->set($key, $value, $ttl === null ? 0 : $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function del($key) {
        return $this->client->del($key);
    }
}
