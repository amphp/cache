<?php

namespace Amp\Cache;

use Amp\Redis\Client;
use Amp\Promise;

class RedisCache implements Cache {
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
    public function get(string $key): Promise {
        return $this->client->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = null): Promise {
        return $this->client->set($key, $value, $ttl === null ? 0 : $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function del(string $key): Promise {
        return $this->client->del($key);
    }
}
