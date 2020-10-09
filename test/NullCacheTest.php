<?php

namespace Amp\Cache\Test;

use Amp\Cache\NullCache;
use Amp\PHPUnit\AsyncTestCase;

class NullCacheTest extends AsyncTestCase
{
    public function test()
    {
        $cache = new NullCache;
        $cache->set("foo", "bar");
        $this->assertNull($cache->get("foo"));
        $this->assertFalse($cache->delete("foo"));
    }
}
