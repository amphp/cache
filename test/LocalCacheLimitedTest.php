<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;

class LocalCacheLimitedTest extends CacheTest
{
    public function testEntryIsNotReturnedAfterCacheLimitReached(): void
    {
        $cache = $this->createCache();

        for ($i = 1; $i <= 6; $i++) {
            $cache->set("foo_$i", $i, 0);
        }

        self::assertNull($cache->get("foo_1"));
    }

    protected function createCache(): Cache
    {
        return new LocalCache(5, 5);
    }
}
