<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\Cache;
use Amp\Cache\PrefixCache;

class PrefixCacheTest extends CacheTest {
    /** @return PrefixCache */
    protected function createCache(): Cache {
        return new PrefixCache(new ArrayCache, "prefix.");
    }

    public function testPrefix() {
        $this->assertSame("prefix.", $this->createCache()->getKeyPrefix());
    }
}
