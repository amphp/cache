<?php

namespace Amp\Cache\Test;

use Amp\Cache\LocalCache;
use Amp\Cache\StringCache;
use Amp\Cache\StringCacheAdapter;

class LocalCacheLimitedTest extends StringCacheTest
{
    public function testEntryIsNotReturnedAfterCacheLimitReached(): void
    {
        $cache = $this->createCache();

        for ($i = 1; $i <= 6; $i++) {
            $cache->set("foo_$i", $i, 0);
        }

        self::assertNull($cache->get("foo_1"));
    }

    protected function createCache(): StringCache
    {
        return new StringCacheAdapter(new LocalCache(5));
    }
}
