<?php

namespace Amp\Cache;

use Amp\Failure;
use Amp\Promise;
use Amp\Serialization\SerializationException;
use Amp\Serialization\Serializer;
use function Amp\call;

final class SerializedCache
{
    /** @var Cache */
    private $cache;

    /** @var Serializer */
    private $serializer;

    public function __construct(Cache $cache, Serializer $serializer)
    {
        $this->cache = $cache;
        $this->serializer = $serializer;
    }

    /**
     * The promise is resolved with the unserialized cached value.
     *
     * @param $key string Cache key.
     *
     * @return Promise<mixed|null> Resolves to the cached value nor `null` if it doesn't exist or fails with a
     * CacheException or SerializationException on failure.
     */
    public function get(string $key): Promise
    {
        return call(function () use ($key) {
            $data = yield $this->cache->get($key);
            if ($data === null) {
                return $data;
            }

            return $this->serializer->unserialize($data);
        });
    }

    /**
     * Allows any serializable value to cached, not just strings.
     *
     * @param $key   string Cache key.
     * @param $value mixed Value to cache.
     * @param $ttl   int Timeout in seconds. The default `null` $ttl value indicates no timeout. Values less than 0 MUST
     *               throw an \Error.
     *
     * @return Promise<void> Resolves either successfully or fails with a CacheException or SerializationException
     * on failure.
     */
    public function set(string $key, $value, int $ttl = null): Promise
    {
        if ($value === null) {
            return new Failure(new CacheException('Cannot store NULL in serialized cache'));
        }

        try {
            $value = $this->serializer->serialize($value);
        } catch (SerializationException $exception) {
            return new Failure($exception);
        }

        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * Deletes a value associated with the given key if it exists.
     *
     * @param $key string Cache key.
     *
     * @return Promise<bool> Resolves to `true` / `false` to indicate whether the key existed or fails with a
     * CacheException on failure.
     */
    public function delete(string $key): Promise
    {
        return $this->cache->delete($key);
    }
}
