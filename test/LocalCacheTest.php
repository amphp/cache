<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;

class LocalCacheTest extends CacheTest
{
    protected function createCache(): Cache
    {
        return new LocalCache;
    }
}
