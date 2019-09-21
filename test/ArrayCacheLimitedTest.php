<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\Cache;
use Amp\Loop;

class ArrayCacheLimitedTest extends CacheTest
{
    protected function createCache(): Cache
    {
        return new ArrayCache(5000, 5);
    }

    public function testEntryIsntReturnedAfterCacheLimitReached()
    {
        Loop::run(function () {
            $cache = $this->createCache();

            for ($i = 1; $i <= 6; $i++) {
                yield $cache->set("foo_$i", $i, 0);
            }

            $this->assertNull(yield $cache->get("foo_1"));
        });
    }
}
