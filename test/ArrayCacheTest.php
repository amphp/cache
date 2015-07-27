<?php

namespace Amp\Cache\Test;

use Amp\NativeReactor;
use Amp\Cache\ArrayCache;

class ArrayCacheDestructorStub extends ArrayCache {
    public function __destruct() {
        parent::__destruct();
        echo "destruct";
    }
}

class ArrayCacheTest extends \PHPUnit_Framework_TestCase {

    public function testGcWhenActiveCacheEntriesExists() {
        $this->expectOutputString("destruct");
        $reactor = new NativeReactor;
        $reactor->run(function ($reactor) {
            $cache = new ArrayCacheDestructorStub($reactor);
            $cache->set("mykey", "myvalue", 0);
        });
        $info = $reactor->__debugInfo();
        $this->assertSame(0, $info["timers"]);
    }

    public function testHas() {
        (new NativeReactor)->run(function ($reactor) {
            $cache = new ArrayCache($reactor);

            $promise = $cache->has("mykey");
            $this->assertInstanceOf("Amp\Success", $promise);
            $result = (yield $promise);
            $this->assertFalse($result);

            $promise = $cache->set("mykey", "myvalue", 10);
            $this->assertInstanceOf("Amp\Success", $promise);
            yield $promise;

            $this->assertTrue(yield $cache->has("mykey"));
            yield $cache->del("mykey");
        });
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage No cache entry exists at key "mykey"
     */
    public function testGetThrowsOnNonexistentKey() {
        (new NativeReactor)->run(function ($reactor) {
            $cache = new ArrayCache($reactor);
            $result = (yield $cache->get("mykey"));
        });
    }

    public function testGet() {
        (new NativeReactor)->run(function ($reactor) {
            $cache = new ArrayCache($reactor);
            yield $cache->set("mykey", "myvalue");
            $promise = $cache->get("mykey");
            $this->assertInstanceOf("Amp\Success", $promise);
            $this->assertSame("myvalue", (yield $promise));
            yield $cache->del("mykey");
        });
    }
    
    /**
     * @dataProvider provideBadTtls
     * @expectedException \DomainException
     * @expectedExceptionMessage Invalid cache TTL; integer >= 0 or null required
     */
    public function testSetFailsOnInvalidTtl($badTtl) {
        (new NativeReactor)->run(function ($reactor) use ($badTtl) {
            $cache = new ArrayCache($reactor);
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


















