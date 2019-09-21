<?php

namespace Amp\Cache\Test;

use Amp\Cache\NullCache;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

class NullCacheTest extends TestCase
{
    public function test()
    {
        Loop::run(function () {
            $cache = new NullCache;
            $this->assertNull(yield $cache->set("foo", "bar"));
            $this->assertNull(yield $cache->get("foo"));
            $this->assertFalse(yield $cache->delete("foo"));
        });
    }
}
