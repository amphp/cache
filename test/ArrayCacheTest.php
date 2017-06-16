<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class ArrayCacheTest extends TestCase {
    public function testGet() {
        Loop::run(function () {
            $cache = new ArrayCache;

            $result = yield $cache->get("mykey");
            $this->assertNull($result);

            yield $cache->set("mykey", "myvalue", 10);

            $result = yield $cache->get("mykey");
            $this->assertSame("myvalue", $result);
        });
    }

    public function testEntryIsntReturnedAfterTTLHasPassed() {
        Loop::run(function () {
            $cache = new ArrayCache;

            yield $cache->set("foo", "bar", 0);
            sleep(1);

            $this->assertNull(yield $cache->get("foo"));
        });
    }

    public function testEntryIsReturnedWhenOverriddenWithNoTimeout() {
        Loop::run(function () {
            $cache = new ArrayCache;

            yield $cache->set("foo", "bar", 0);
            yield $cache->set("foo", "bar");
            sleep(1);

            $this->assertNotNull(yield $cache->get("foo"));
        });
    }

    public function testEntryIsntReturnedAfterDelete() {
        Loop::run(function () {
            $cache = new ArrayCache;

            yield $cache->set("foo", "bar");
            yield $cache->delete("foo");

            $this->assertNull(yield $cache->get("foo"));
        });
    }

    /**
     * @dataProvider provideBadTTLs
     * @expectedException \Error
     */
    public function testSetFailsOnInvalidTTL($badTTL) {
        Loop::run(function () use ($badTTL) {
            $cache = new ArrayCache;
            $cache->set("mykey", "myvalue", $badTTL);
        });
    }

    public function provideBadTTLs() {
        return [
            [-1],
            [new \StdClass],
            [[]],
        ];
    }
}
