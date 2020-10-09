<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

abstract class CacheTest extends AsyncTestCase
{
    abstract protected function createCache(): Cache;

    public function testGet()
    {
        $cache = $this->createCache();

        $result = $cache->get("mykey");
        $this->assertNull($result);

        $cache->set("mykey", "myvalue", 10);

        $result = $cache->get("mykey");
        $this->assertSame("myvalue", $result);
    }

    public function testEntryIsNotReturnedAfterTTLHasPassed()
    {
        $cache = $this->createCache();

        $cache->set("foo", "bar", 0);
        delay(1000);

        $this->assertNull($cache->get("foo"));
    }

    public function testEntryIsReturnedWhenOverriddenWithNoTimeout()
    {
        $cache = $this->createCache();

        $cache->set("foo", "bar", 0);
        $cache->set("foo", "bar");
        delay(1000);

        $this->assertNotNull($cache->get("foo"));
    }

    public function testEntryIsNotReturnedAfterDelete()
    {
        $cache = $this->createCache();

        $cache->set("foo", "bar");
        $cache->delete("foo");

        $this->assertNull($cache->get("foo"));
    }
}
