<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\Cache;

class ArrayCacheTest extends CacheTest
{
    protected function createCache(): Cache
    {
        return new ArrayCache;
    }
}
