<?php

namespace Amp\Cache;

use Amp\Promise;

interface Cache {
    /**
     * Checks if a value associated with the given key exists.
     *
     * @param $key string
     * @return Promise
     */
    public function has ($key);

    /**
     * Gets a value associated with the given key. If it doesn't exist, the Promise will fail.
     *
     * @param $key string
     * @return Promise
     */
    public function get ($key);

    /**
     * Sets a value associated with the given key. Overrides a existing value if there's one.
     *
     * @param $key string
     * @param $value string
     * @param $ttl int
     * @return Promise
     */
    public function set ($key, $value, $ttl = null);

    /**
     * Deletes a value associated with the given key if it exists.
     *
     * @param $key string
     * @return Promise
     */
    public function del ($key);
}
