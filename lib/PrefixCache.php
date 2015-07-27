<?php

namespace Amp\Cache;

abstract class PrefixCache implements Cache {
    protected $keyPrefix;

    public function __construct($keyPrefix) {
        $this->setKeyPrefix($keyPrefix);
    }

    /**
     * Sets a new prefix.
     *
     * @param $keyPrefix string
     */
    public function setKeyPrefix($keyPrefix) {
        if (!\is_string($keyPrefix)) {
            throw new \InvalidArgumentException(\sprintf(
                "keyPrefix must be string, %s given",
                gettype($keyPrefix)
            ));
        }

        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Gets the current prefix.
     *
     * @return string
     */
    public function getKeyPrefix() {
        return $this->keyPrefix;
    }
}
