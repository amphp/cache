<?php declare(strict_types=1);

namespace Amp\Cache\Test;

use Amp\Cache\LocalCache;
use Amp\Cache\StringCache;
use Amp\Cache\StringCacheAdapter;

class LocalCacheTest extends StringCacheTest
{
    protected function createCache(): StringCache
    {
        return new StringCacheAdapter(new LocalCache);
    }
}
