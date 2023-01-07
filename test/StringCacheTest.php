<?php declare(strict_types=1);

namespace Amp\Cache\Test;

use Amp\Cache\StringCache;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

abstract class StringCacheTest extends AsyncTestCase
{
    public function testGet(): void
    {
        $cache = $this->createCache();

        $result = $cache->get("mykey");
        self::assertNull($result);

        $cache->set("mykey", "myvalue", 10);

        $result = $cache->get("mykey");
        self::assertSame("myvalue", $result);
    }

    public function testEntryIsNotReturnedAfterTTLHasPassed(): void
    {
        $cache = $this->createCache();

        $cache->set("foo", "bar", 0);
        delay(1);

        self::assertNull($cache->get("foo"));
    }

    public function testEntryIsReturnedWhenOverriddenWithNoTimeout(): void
    {
        $cache = $this->createCache();

        $cache->set("foo", "bar", 0);
        $cache->set("foo", "bar");
        delay(1);

        self::assertNotNull($cache->get("foo"));
    }

    public function testEntryIsNotReturnedAfterDelete(): void
    {
        $cache = $this->createCache();

        $cache->set("foo", "bar");
        $cache->delete("foo");

        self::assertNull($cache->get("foo"));
    }

    abstract protected function createCache(): StringCache;
}
