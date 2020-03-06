<?php

namespace Amp\Cache\Test;

use Amp\Cache\NullCache;
use Amp\PHPUnit\AsyncTestCase;

class NullCacheTest extends AsyncTestCase
{
    public function test(): \Generator
    {
        $cache = new NullCache;
        $this->assertNull(yield $cache->set("foo", "bar"));
        $this->assertNull(yield $cache->get("foo"));
        $this->assertFalse(yield $cache->delete("foo"));
    }
}
