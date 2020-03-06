<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;

abstract class CacheTest extends AsyncTestCase
{
    abstract protected function createCache(): Cache;

    public function testGet(): \Generator
    {
        $cache = $this->createCache();

        $result = yield $cache->get("mykey");
        $this->assertNull($result);

        yield $cache->set("mykey", "myvalue", 10);

        $result = yield $cache->get("mykey");
        $this->assertSame("myvalue", $result);
    }

    public function testEntryIsNotReturnedAfterTTLHasPassed(): \Generator
    {
        $cache = $this->createCache();

        yield $cache->set("foo", "bar", 0);
        yield new Delayed(1000);

        $this->assertNull(yield $cache->get("foo"));
    }

    public function testEntryIsReturnedWhenOverriddenWithNoTimeout(): \Generator
    {
        $cache = $this->createCache();

        yield $cache->set("foo", "bar", 0);
        yield $cache->set("foo", "bar");
        yield new Delayed(1000);

        $this->assertNotNull(yield $cache->get("foo"));
    }

    public function testEntryIsNotReturnedAfterDelete(): \Generator
    {
        $cache = $this->createCache();

        yield $cache->set("foo", "bar");
        yield $cache->delete("foo");

        $this->assertNull(yield $cache->get("foo"));
    }
}
