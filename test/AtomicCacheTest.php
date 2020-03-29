<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\AtomicCache;
use Amp\Cache\CacheException;
use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Serialization\NativeSerializer;
use Amp\Sync\LocalKeyedMutex;

class AtomicCacheTest extends AsyncTestCase
{
    public function testLoadNoValue(): \Generator
    {
        $this->setMinimumRuntime(100);

        $internalCache = new ArrayCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(100, 'value');
        };

        $result = yield $atomicCache->load('key', $callback);

        $this->assertSame('value', $result);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testLoadExistingValue(): \Generator
    {
        $this->setTimeout(100);

        $internalCache = new ArrayCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $internalCache->set('key', 'value');

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(100, 'value');
        };

        $result = yield $atomicCache->load('key', $callback);

        $this->assertSame('value', $result);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testSwap(): \Generator
    {
        $this->setMinimumRuntime(100);

        $internalCache = new ArrayCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $internalCache->set('key', 'original');

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(100, 'updated');
        };

        $result = yield $atomicCache->swap('key', $callback);

        $this->assertSame('updated', $result);
        $this->assertSame('updated', yield $internalCache->get('key'));
    }

    public function testSimultaneousLoad(): \Generator
    {
        $this->setMinimumRuntime(500);

        $internalCache = new ArrayCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(500, 'value');
        };

        $setPromise = $atomicCache->load('key', $callback);

        $getPromise = $atomicCache->load('key', $this->createCallback(0));

        $this->assertSame('value', yield $setPromise);
        $this->assertSame('value', yield $getPromise);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testLoadDuringSwap(): \Generator
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(600);

        $internalCache = new ArrayCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(500, 'value');
        };

        $setPromise = $atomicCache->swap('key', $callback);

        $getPromise = $atomicCache->load('key', $this->createCallback(0));

        $this->assertSame('value', yield $setPromise);
        $this->assertSame('value', yield $getPromise);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testSimultaneousSwap(): \Generator
    {
        $this->setMinimumRuntime(1000);
        $this->setTimeout(1100);

        $internalCache = new ArrayCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $atomicCache->set('key', '0');

        $callback = function (string $key, string $value): Promise {
            $this->assertSame('key', $key);
            return new Delayed(500, (string) (\intval($value) + 1));
        };

        $promise1 = $atomicCache->swap('key', $callback);

        $promise2 = $atomicCache->swap('key', $callback);

        $this->assertSame('1', yield $promise1);
        $this->assertSame('2', yield $promise2);
    }

    public function testLoadCallbackReturningNull(): Promise
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new ArrayCache, new LocalKeyedMutex);
        return $cache->load('key', function () {
            return null;
        });
    }

    public function testSetNull(): Promise
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new ArrayCache, new LocalKeyedMutex);
        return $cache->set('key', null);
    }

    public function provideSerializableValues(): array
    {
        return [
            [new \stdClass],
            [1],
            [true],
            [3.14],
        ];
    }

    /**
     * @dataProvider provideSerializableValues
     */
    public function testLoadCallbackReturningSerializableValue($value): \Generator
    {
        $cache = new AtomicCache(new ArrayCache, new LocalKeyedMutex, new NativeSerializer);

        $result = yield $cache->load('key', function () use ($value) {
            return $value;
        });

        $this->assertEquals($value, $result);
        $this->assertEquals($value, yield $cache->get('key'));
    }

    /**
     * @dataProvider provideSerializableValues
     */
    public function testSwapLoadCallbackReturningNonString($value): \Generator
    {
        $cache = new AtomicCache(new ArrayCache, new LocalKeyedMutex, new NativeSerializer);

        $result = yield $cache->swap('key', function () use ($value) {
            return $value;
        });

        $this->assertEquals($value, $result);
        $this->assertEquals($value, yield $cache->get('key'));
    }
}
