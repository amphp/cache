<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class ArrayCacheTest extends TestCase {
    public function testGet() {
        Loop::run(function () {
            $cache = new ArrayCache;

            $promise = $cache->get("mykey");
            $result = yield $promise;
            $this->assertNull($result);

            yield $cache->set("mykey", "myvalue", 10);

            $result = yield $cache->get("mykey");
            $this->assertSame("myvalue", $result);
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
