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

    public function testKeyType(): void
    {
        $cache = new LocalCache();
        $cache->set('123', 'foobar');

        foreach ($cache as $key => $value) {
            // set variables
        }

        self::assertSame('123', $key ?? null);
        self::assertSame('foobar', $value ?? null);
    }
}
