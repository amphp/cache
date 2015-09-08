<?php

namespace Amp\Cache;

interface Cache {
    /**
     * Gets a value associated with the given key.
     *
     * If the specified key doesn't exist implementations MUST succeed the resulting promise with NULL.
     *
     * @param $key string
     * @return \Amp\Promise
     */
    public function get($key);

    /**
     * Sets a value associated with the given key. Overrides existing values (if they exist).
     *
     * Implementations MUST NOT accept NULL as value as it's reserved for missing values and
     * should not have any other meaning. TTL values are measured in seconds.
     *
     * The default NULL $ttl value indicates no timeout.
     *
     * The eventual resolution value of the resulting promise is unimportant. The success
     * or failure of the promise indicates the operation's success.
     *
     * @param $key string
     * @param $value string
     * @param $ttl int
     * @return \Amp\Promise
     */
    public function set($key, $value, $ttl = null);

    /**
     * Deletes a value associated with the given key if it exists.
     *
     * The eventual resolution value of the resulting promise is unimportant. However,
     * implementations SHOULD return boolean TRUE or FALSE to indicate if the specified
     * key existed at the time the delete operation was request.
     *
     * The ultimate success or failure of the promise indicates the operation's success, though.
     * Implementations MUST transparently succeed operations for non-existent keys.
     *
     * @param $key string
     * @return \Amp\Promise
     */
    public function del($key);
}
