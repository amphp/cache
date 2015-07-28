<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;

class ArrayCacheDestructorStub extends ArrayCache {
    public function __destruct() {
        parent::__destruct();
        echo "destruct";
    }
}

class ArrayCacheTest extends \PHPUnit_Framework_TestCase {
    protected function setUp() {
        \Amp\reactor(\Amp\driver());
    }

    public function testGcWhenActiveCacheEntriesExists() {
        $this->expectOutputString("destruct");
        \Amp\run(function () {
            $cache = new ArrayCacheDestructorStub();
            $cache->set("mykey", "myvalue", 0);
        });
    }

    public function testHas() {
        \Amp\run(function () {
            $cache = new ArrayCache;

            $promise = $cache->has("mykey");
            $this->assertInstanceOf("Amp\Success", $promise);
            $result = (yield $promise);
            $this->assertFalse($result);

            $promise = $cache->set("mykey", "myvalue", 10);
            $this->assertInstanceOf("Amp\Success", $promise);
            yield $promise;

            $this->assertTrue(yield $cache->has("mykey"));
        });
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage No cache entry exists at key "mykey"
     */
    public function testGetThrowsOnNonexistentKey() {
        \Amp\run(function () {
            $cache = new ArrayCache;
            $result = (yield $cache->get("mykey"));
        });
    }

    public function testGet() {
        \Amp\run(function () {
            $cache = new ArrayCache;
            yield $cache->set("mykey", "myvalue");
            $promise = $cache->get("mykey");
            $this->assertInstanceOf("Amp\Success", $promise);
            $this->assertSame("myvalue", (yield $promise));
        });
    }

    /**
     * @dataProvider provideBadTtls
     * @expectedException \DomainException
     * @expectedExceptionMessage Invalid cache TTL; integer >= 0 or null required
     */
    public function testSetFailsOnInvalidTtl($badTtl) {
        \Amp\run(function () use ($badTtl) {
            $cache = new ArrayCache;
            yield $cache->set("mykey", "myvalue", $badTtl);
        });
    }

    public function provideBadTtls() {
        return [
            [-1],
            [new \StdClass],
            [[]],
        ];
    }
}
