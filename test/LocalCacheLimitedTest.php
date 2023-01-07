<?php declare(strict_types=1);

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
            $cache->set("foo_$i", (string) $i, 0);
        }

        self::assertNull($cache->get("foo_1"));
    }

    public function testLruEntryIsNotReturnedAfterCacheLimitReached(): void
    {
        $cache = $this->createCache();

        for ($i = 1; $i <= 5; $i++) {
            $cache->set("foo_$i", (string) $i, 0);
        }

        $cache->get("foo_1"); // Touch foo_1 to mark recently used
        $cache->set("foo_6", '6'); // Add another key to exceed cache size limit

        self::assertNull($cache->get("foo_2"));
    }

    protected function createCache(): StringCache
    {
        return new StringCacheAdapter(new LocalCache(5));
    }
}
