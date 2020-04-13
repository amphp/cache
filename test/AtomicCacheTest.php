<?php

namespace Amp\Cache\Test;

use Amp\Cache\ArrayCache;
use Amp\Cache\AtomicCache;
use Amp\Cache\CacheException;
use Amp\Cache\SerializedCache;
use Amp\Delayed;
use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Serialization\NativeSerializer;
use Amp\Serialization\PassthroughSerializer;
use Amp\Sync\KeyedMutex;
use Amp\Sync\LocalKeyedMutex;

class AtomicCacheTest extends AsyncTestCase
{
    public function testComputeIfAbsentWhenValueAbsent(): \Generator
    {
        $this->setMinimumRuntime(100);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(100, 'value');
        };

        $result = yield $atomicCache->computeIfAbsent('key', $callback);

        $this->assertSame('value', $result);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testComputeIfAbsentWhenValueExists(): \Generator
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $internalCache->set('key', 'value');

        $result = yield $atomicCache->computeIfAbsent('key', $this->createCallback(0));

        $this->assertSame('value', $result);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testComputeIfPresentWhenValueAbsent(): \Generator
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $result = yield $atomicCache->computeIfPresent('key', $this->createCallback(0));

        $this->assertNull($result);
    }

    public function testComputeIfPresentWhenValueExists(): \Generator
    {
        $this->setMinimumRuntime(100);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $internalCache->set('key', 'value');

        $callback = function (string $key, string $value): Promise {
            $this->assertSame('key', $key);
            $this->assertSame('value', $value);
            return new Delayed(100, 'new-value');
        };

        $result = yield $atomicCache->computeIfPresent('key', $callback);

        $this->assertSame('new-value', $result);
    }

    public function testCompute(): \Generator
    {
        $this->setMinimumRuntime(100);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $internalCache->set('key', 'original');

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(100, 'updated');
        };

        $result = yield $atomicCache->compute('key', $callback);

        $this->assertSame('updated', $result);
        $this->assertSame('updated', yield $internalCache->get('key'));
    }

    public function testComputeCallbackThrowing(): Promise
    {
        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), new LocalKeyedMutex);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Exception thrown while creating');

        return $cache->compute('key', function () {
            throw new \Exception;
        });
    }

    public function testSimultaneousComputeIfAbsent(): \Generator
    {
        $this->setMinimumRuntime(500);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(500, 'value');
        };

        $setPromise = $atomicCache->computeIfAbsent('key', $callback);

        $getPromise = $atomicCache->computeIfAbsent('key', $this->createCallback(0));

        $this->assertSame('value', yield $setPromise);
        $this->assertSame('value', yield $getPromise);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testComputeIfAbsentDuringCompute(): \Generator
    {
        $this->setMinimumRuntime(500);
        $this->setTimeout(600);

        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): Promise {
            $this->assertSame('key', $key);
            return new Delayed(500, 'value');
        };

        $setPromise = $atomicCache->compute('key', $callback);

        $getPromise = $atomicCache->computeIfAbsent('key', $this->createCallback(0));

        $this->assertSame('value', yield $setPromise);
        $this->assertSame('value', yield $getPromise);
        $this->assertSame('value', yield $internalCache->get('key'));
    }

    public function testSimultaneousCompute(): \Generator
    {
        $this->setMinimumRuntime(1000);
        $this->setTimeout(1100);

        $internalCache = new SerializedCache(new ArrayCache, new NativeSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        yield $atomicCache->set('key', 0);

        $callback = function (string $key, int $value): Promise {
            $this->assertSame('key', $key);
            $this->assertIsInt($value);
            return new Delayed(500, $value + 1);
        };

        $promise1 = $atomicCache->compute('key', $callback);

        $promise2 = $atomicCache->compute('key', $callback);

        $this->assertSame(1, yield $promise1);
        $this->assertSame(2, yield $promise2);
    }

    public function testComputeCallbackReturningNull(): Promise
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), new LocalKeyedMutex);
        return $cache->compute('key', function () {
            return null;
        });
    }

    public function testSetIfAbsent(): \Generator
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $result = yield $atomicCache->setIfAbsent('key', 'value');

        $this->assertSame('value', $result);
        $this->assertSame('value', yield $atomicCache->get('key'));

        $result = yield $atomicCache->setIfAbsent('key', 'new-value');

        $this->assertSame('value', $result);
        $this->assertSame('value', yield $atomicCache->get('key'));
    }

    public function testSetNull(): Promise
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), new LocalKeyedMutex);
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
    public function testComputeCallbackReturningSerializableValue($value): \Generator
    {
        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new NativeSerializer), new LocalKeyedMutex);

        $result = yield $cache->compute('key', function () use ($value) {
            return $value;
        });

        $this->assertEquals($value, $result);
        $this->assertEquals($value, yield $cache->get('key'));
    }

    /**
     * @dataProvider provideSerializableValues
     */
    public function testComputeCallbackReturningNonString($value): \Generator
    {
        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new NativeSerializer), new LocalKeyedMutex);

        $result = yield $cache->compute('key', function () use ($value) {
            return $value;
        });

        $this->assertEquals($value, $result);
        $this->assertEquals($value, yield $cache->get('key'));
    }

    public function testGetOrDefault(): \Generator
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $this->assertSame('default', yield $atomicCache->get('key', 'default'));

        yield $internalCache->set('key', 'value');

        $this->assertSame('value', yield $atomicCache->get('key', 'default'));
    }

    public function testFailingMutex(): Promise
    {
        $mutex = $this->createMock(KeyedMutex::class);
        $mutex->method('acquire')
            ->willReturn(new Failure(new \Exception));

        $cache = new AtomicCache(new SerializedCache(new ArrayCache, new PassthroughSerializer), $mutex);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Exception thrown when obtaining the lock');

        return $cache->compute('key', $this->createCallback(0));
    }

    public function testDelete(): \Generator
    {
        $internalCache = new SerializedCache(new ArrayCache, new PassthroughSerializer);
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $setPromise = $atomicCache->setIfAbsent('key', 'value');

        $computePromise = $atomicCache->computeIfPresent('key', function (): string {
            return 'new-value';
        });

        $deletePromise = $atomicCache->delete('key');

        $this->assertSame('value', yield $setPromise);
        $this->assertSame('new-value', yield $computePromise);
        $this->assertTrue(yield $deletePromise);
    }
}
