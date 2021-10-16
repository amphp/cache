<?php

namespace Amp\Cache\Test;

use Amp\Cache\NullCache;
use Amp\PHPUnit\AsyncTestCase;

class NullCacheTest extends AsyncTestCase
{
    public function test(): void
    {
        $cache = new NullCache;
        $cache->set("foo", "bar");
        self::assertNull($cache->get("foo"));
        self::assertFalse($cache->delete("foo"));
    }
}
