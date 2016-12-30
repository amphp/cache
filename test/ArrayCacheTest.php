<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Interop\Async\Loop;

class ArrayCacheDestructorStub extends ArrayCache {
    public function __destruct() {
        parent::__destruct();
        echo "destruct";
    }
}

class ArrayCacheTest extends \PHPUnit_Framework_TestCase {
    public function loop($cb) {
        \Interop\Async\Loop::execute(function() use ($cb) {
            $gen = $cb();
            if ($gen instanceof \Generator) {
                \Amp\rethrow(new \Amp\Coroutine($gen));
            }
        });
    }
    
    public function testGcWhenActiveCacheEntriesExists() {
        $this->expectOutputString("destruct");
        $this->loop(function () {
            $cache = new ArrayCacheDestructorStub();
            $cache->set("mykey", "myvalue", 0);
        });
    }

    public function testGet() {
        $this->loop(function () {
            $cache = new ArrayCache;

            $promise = $cache->get("mykey");
            $this->assertInstanceOf("Amp\\Success", $promise);
            $result = (yield $promise);
            $this->assertNull($result);

            $promise = $cache->set("mykey", "myvalue", 10);
            $this->assertInstanceOf("Amp\\Success", $promise);
            yield $promise;

            $result = (yield $cache->get("mykey"));
            $this->assertNotNull($result);
            $this->assertSame("myvalue", $result);
        });
    }

    /**
     * @dataProvider provideBadTtls
     * @expectedException \Error
     */
    public function testSetFailsOnInvalidTtl($badTtl) {
        $this->loop(function () use ($badTtl) {
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
