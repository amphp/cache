<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\Cache\FileCache;
use Amp\Cache\PrefixCache;
use Amp\Sync\LocalKeyedMutex;

class FileCacheTest extends CacheTest
{
    protected function createCache(): Cache
    {
        return new PrefixCache(new FileCache(\sys_get_temp_dir(), new LocalKeyedMutex()), 'amphp-cache-');
    }
}
