<?php

namespace Amp\Cache;

use Amp\Failure;
use Amp\Promise;
use Amp\Serialization\SerializationException;
use Amp\Serialization\Serializer;
use function Amp\call;

final class DelegatingSerializedCache implements SerializedCache
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

    public function set(string $key, $value, int $ttl = null): Promise
    {
        try {
            $value = $this->serializer->serialize($value);
        } catch (SerializationException $exception) {
            return new Failure($exception);
        }

        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): Promise
    {
        return $this->cache->delete($key);
    }
}
