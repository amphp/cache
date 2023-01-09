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

    public function testSizeSetNew(): void
    {
        $cache = new LocalCache(2);
        $cache->set('1', 'foobar1');
        $cache->set('2', 'foobar2');
        $cache->set('3', 'foobar3');

        $keys = [];
        $values = [];

        foreach ($cache as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }

        self::assertSame(['2', '3'], $keys);
        self::assertSame(['foobar2', 'foobar3'], $values);
    }

    public function testSizeSetExisting(): void
    {
        $cache = new LocalCache(2);
        $cache->set('1', 'foobar1');
        $cache->set('2', 'foobar2');
        $cache->set('2', 'foobar3');

        $keys = [];
        $values = [];

        foreach ($cache as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }

        self::assertSame(['1', '2'], $keys);
        self::assertSame(['foobar1', 'foobar3'], $values);
    }
}
