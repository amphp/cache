<?php

namespace Amp\Cache;

use Amp\Promise;
use Amp\Redis\Client;

class Redis extends PrefixCache {
    /** @var Client */
    private $client;

    /**
     * Constructs a new instance using the given client and key prefix.
     *
     * @param Client $client
     * @param $keyPrefix
     */
    public function __construct (Client $client, $keyPrefix) {
        parent::__construct($keyPrefix);
        $this->client = $client;
    }

    /**
     * Checks if a value associated with the given key exists.
     *
     * @param $key string
     * @return Promise
     */
    public function has ($key) {
        return $this->client->exists($this->keyPrefix . $key);
    }

    /**
     * Gets a value associated with the given key. If it doesn't exist, the Promise will fail.
     *
     * @param $key string
     * @return Promise
     */
    public function get ($key) {
        return $this->client->get($this->keyPrefix . $key);
    }

    /**
     * Sets a value associated with the given key. Overrides a existing value if there's one.
     *
     * @param $key string
     * @param $value string
     * @param $ttl int
     * @return Promise
     */
    public function set ($key, $value, $ttl = 0) {
        return $this->client->set($this->keyPrefix . $key, $value, $ttl);
    }

    /**
     * Deletes a value associated with the given key if it exists.
     *
     * @param $key string
     * @return Promise
     */
    public function del ($key) {
        return $this->client->del($this->keyPrefix . $key);
    }
}
