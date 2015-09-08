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

    public function testGet() {
        \Amp\run(function () {
            $cache = new ArrayCache;

            $promise = $cache->get("mykey");
            $this->assertInstanceOf("Amp\\Success", $promise);
            $result = (yield $promise);
            $this->assertNull($result);

            $promise = $cache->set("mykey", "myvalue", 10);
            $this->assertInstanceOf("Amp\\Success", $promise);
            yield $promise;

            $result = yield $cache->get("mykey");
            $this->assertNotNull($result);
            $this->assertSame("myvalue", $result);
        });
    }

    /**
     * @dataProvider provideBadTtls
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid cache TTL; integer >= 0 or null required
     */
    public function testSetFailsOnInvalidTtl($badTtl) {
        \Amp\run(function () use ($badTtl) {
            $cache = new ArrayCache;
            $cache->set("mykey", "myvalue", $badTtl);
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
