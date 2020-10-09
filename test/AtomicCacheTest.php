<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\AtomicCache;
use Amp\Cache\CacheException;
use Amp\Cache\SerializedCache;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\NativeSerializer;
use Amp\Serialization\PassthroughSerializer;
use Amp\Sync\KeyedMutex;
use Amp\Sync\LocalKeyedMutex;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

class AtomicCacheTest extends AsyncTestCase
{
    public function testComputeIfAbsentWhenValueAbsent()
    {
        $this->setMinimumRuntime(100);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(100);
            return 'value';
        };

        $result = $atomicCache->computeIfAbsent('key', $callback);

        $this->assertSame('value', $result);
        $this->assertSame('value', $internalCache->get('key'));
    }

    public function testComputeIfAbsentWhenValueExists()
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'value');

        $result = $atomicCache->computeIfAbsent('key', $this->createCallback(0));

        $this->assertSame('value', $result);
        $this->assertSame('value', $internalCache->get('key'));
    }

    public function testComputeIfPresentWhenValueAbsent()
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $result = $atomicCache->computeIfPresent('key', $this->createCallback(0));

        $this->assertNull($result);
    }

    public function testComputeIfPresentWhenValueExists()
    {
        $this->setMinimumRuntime(100);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'value');

        $callback = function (string $key, string $value): string {
            $this->assertSame('key', $key);
            $this->assertSame('value', $value);
            delay(100);
            return 'new-value';
        };

        $result = $atomicCache->computeIfPresent('key', $callback);

        $this->assertSame('new-value', $result);
    }

    public function testCompute()
    {
        $this->setMinimumRuntime(100);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'original');

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(100);
            return 'updated';
        };

        $result = $atomicCache->compute('key', $callback);

        $this->assertSame('updated', $result);
        $this->assertSame('updated', $internalCache->get('key'));
    }

    public function testComputeCallbackThrowing()
    {
        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), new LocalKeyedMutex);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Exception thrown while creating');

        $cache->compute('key', function () {
            throw new \Exception;
        });
    }

    public function testSimultaneousComputeIfAbsent()
    {
        $this->setMinimumRuntime(500);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(500);
            return 'value';
        };

        $setPromise = async(fn() => $atomicCache->computeIfAbsent('key', $callback));

        $getPromise = async(fn() => $atomicCache->computeIfAbsent('key', $this->createCallback(0)));

        $this->assertSame('value', await($setPromise));
        $this->assertSame('value', await($getPromise));
        $this->assertSame('value', $internalCache->get('key'));
    }

    public function testComputeIfAbsentDuringCompute()
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(600);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(500);
            return 'value';
        };

        $setPromise = async(fn() => $atomicCache->compute('key', $callback));

        $getPromise = async(fn() => $atomicCache->computeIfAbsent('key', $this->createCallback(0)));

        $this->assertSame('value', await($setPromise));
        $this->assertSame('value', await($getPromise));
        $this->assertSame('value', $internalCache->get('key'));
    }

    public function testSimultaneousCompute()
    {
        $this->setMinimumRuntime(1000);
        $this->setTimeout(1100);

        $internalCache = new SerializedCache(new ArrayCache, new NativeSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $atomicCache->set('key', 0);

        $callback = function (string $key, int $value): int {
            $this->assertSame('key', $key);
            $this->assertIsInt($value);
            delay(500);
            return $value + 1;
        };

        $promise1 = async(fn() => $atomicCache->compute('key', $callback));

        $promise2 = async(fn() => $atomicCache->compute('key', $callback));

        $this->assertSame(1, await($promise1));
        $this->assertSame(2, await($promise2));
    }

    public function testComputeCallbackReturningNull()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), new LocalKeyedMutex);
        $cache->compute('key', function () {
            return null;
        });
    }

    public function testSetNull()
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), new LocalKeyedMutex);
        $cache->set('key', null);
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
    public function testComputeCallbackReturningSerializableValue($value)
    {
        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new NativeSerializer), new LocalKeyedMutex);

        $result = $cache->compute('key', function () use ($value) {
            return $value;
        });

        $this->assertEquals($value, $result);
        $this->assertEquals($value, $cache->get('key'));
    }

    /**
     * @dataProvider provideSerializableValues
     */
    public function testComputeCallbackReturningNonString($value)
    {
        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new NativeSerializer), new LocalKeyedMutex);

        $result = $cache->compute('key', function () use ($value) {
            return $value;
        });

        $this->assertEquals($value, $result);
        $this->assertEquals($value, $cache->get('key'));
    }

    public function testGetOrDefault()
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $this->assertSame('default', $atomicCache->get('key', 'default'));

        $internalCache->set('key', 'value');

        $this->assertSame('value', $atomicCache->get('key', 'default'));
    }

    public function testFailingMutex()
    {
        $mutex = $this->createMock(KeyedMutex::class);
        $mutex->method('acquire')
            ->willThrowException(new \Exception);

        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), $mutex);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Exception thrown when obtaining the lock');

        $cache->compute('key', $this->createCallback(0));
    }

    public function testDelete()
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $atomicCache->set('key', 'value');

        $computePromise = async(fn() => $atomicCache->computeIfPresent('key', function (): string {
            return 'new-value';
        }));

        $deletePromise = async(fn() => $atomicCache->delete('key'));

        $this->assertSame('new-value', await($computePromise));
        $this->assertTrue(await($deletePromise));
    }
}
