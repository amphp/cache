<?php

namespace Amp\Cache\Test;

use Amp\Cache\LocalCache;
use Amp\Cache\PrefixCache;
use Amp\Cache\StringCache;
use Amp\Cache\StringCacheAdapter;

class PrefixCacheTest extends StringCacheTest
{
    public function testPrefix(): void
    {
        self::assertSame("prefix.", (new PrefixCache(new LocalCache, "prefix."))->getKeyPrefix());
    }

    /** @return PrefixCache */
    protected function createCache(): StringCache
    {
        return new StringCacheAdapter(new PrefixCache(new LocalCache, "prefix."));
    }
}
