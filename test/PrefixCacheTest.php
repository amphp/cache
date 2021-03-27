<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\Cache;
use Amp\Cache\PrefixCache;

class PrefixCacheTest extends CacheTest
{
    public function testPrefix(): void
    {
        self::assertSame("prefix.", $this->createCache()->getKeyPrefix());
    }

    /** @return PrefixCache */
    protected function createCache(): Cache
    {
        return new PrefixCache(new ArrayCache, "prefix.");
    }
}
