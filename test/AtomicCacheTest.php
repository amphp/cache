<?php

namespace Amp\Cache\Test;

use Amp\Cache\AtomicCache;
use Amp\Cache\CacheException;
use Amp\Cache\LocalCache;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\KeyedMutex;
use Amp\Sync\LocalKeyedMutex;
use function Amp\async;
use function Amp\delay;

class AtomicCacheTest extends AsyncTestCase
{
    public function testComputeIfAbsentWhenValueAbsent(): void
    {
        $this->setMinimumRuntime(0.1);

        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(0.1);
            return 'value';
        };

        $result = $atomicCache->computeIfAbsent('key', $callback);

        self::assertSame('value', $result);
        self::assertSame('value', $internalCache->get('key'));
    }

    public function testComputeIfAbsentWhenValueExists(): void
    {
        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'value');

        $result = $atomicCache->computeIfAbsent('key', $this->createCallback(0));

        self::assertSame('value', $result);
        self::assertSame('value', $internalCache->get('key'));
    }

    public function testComputeIfPresentWhenValueAbsent(): void
    {
        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $result = $atomicCache->computeIfPresent('key', $this->createCallback(0));

        self::assertNull($result);
    }

    public function testComputeIfPresentWhenValueExists(): void
    {
        $this->setMinimumRuntime(0.1);

        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'value');

        $callback = function (string $key, string $value): string {
            $this->assertSame('key', $key);
            $this->assertSame('value', $value);
            delay(0.1);
            return 'new-value';
        };

        $result = $atomicCache->computeIfPresent('key', $callback);

        self::assertSame('new-value', $result);
    }

    public function testCompute(): void
    {
        $this->setMinimumRuntime(0.1);

        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'original');

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(0.1);
            return 'updated';
        };

        $result = $atomicCache->compute('key', $callback);

        self::assertSame('updated', $result);
        self::assertSame('updated', $internalCache->get('key'));
    }

    public function testComputeCallbackThrowing(): void
    {
        $cache = new AtomicCache(new LocalCache, new LocalKeyedMutex);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Exception thrown while creating');

        $cache->compute('key', function () {
            throw new \Exception;
        });
    }

    public function testSimultaneousComputeIfAbsent(): void
    {
        $this->setMinimumRuntime(0.5);

        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(0.5);
            return 'value';
        };

        $setPromise = async(fn () => $atomicCache->computeIfAbsent('key', $callback));

        $getPromise = async(fn () => $atomicCache->computeIfAbsent('key', $this->createCallback(0)));

        self::assertSame('value', $setPromise->await());
        self::assertSame('value', $getPromise->await());
        self::assertSame('value', $internalCache->get('key'));
    }

    public function testComputeIfAbsentDuringCompute(): void
    {
        $this->setMinimumRuntime(0.5);
        $this->setTimeout(0.6);

        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $callback = function (string $key): string {
            $this->assertSame('key', $key);
            delay(0.5);
            return 'value';
        };

        $setPromise = async(fn () => $atomicCache->compute('key', $callback));

        $getPromise = async(fn () => $atomicCache->computeIfAbsent('key', $this->createCallback(0)));

        self::assertSame('value', $setPromise->await());
        self::assertSame('value', $getPromise->await());
        self::assertSame('value', $internalCache->get('key'));
    }

    public function testSimultaneousCompute(): void
    {
        $this->setMinimumRuntime(1);
        $this->setTimeout(1.3);

        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 0);

        $callback = function (string $key, int $value): int {
            $this->assertSame('key', $key);
            $this->assertIsInt($value);
            delay(0.5);
            return $value + 1;
        };

        $promise1 = async(fn () => $atomicCache->compute('key', $callback));

        $promise2 = async(fn () => $atomicCache->compute('key', $callback));

        self::assertSame(1, $promise1->await());
        self::assertSame(2, $promise2->await());
    }

    public function testComputeCallbackReturningNull(): void
    {
        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Cannot store NULL');

        $cache = new AtomicCache(new LocalCache, new LocalKeyedMutex);
        $cache->compute('key', function () {
            return null;
        });
    }

    public function testFailingMutex(): void
    {
        $mutex = $this->createMock(KeyedMutex::class);
        $mutex->method('acquire')
            ->willThrowException(new \Exception);

        $cache = new AtomicCache(new LocalCache, $mutex);

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('Exception thrown when obtaining the lock');

        $cache->compute('key', $this->createCallback(0));
    }

    public function testDelete(): void
    {
        $internalCache = new LocalCache;
        $atomicCache = new AtomicCache($internalCache, new LocalKeyedMutex);

        $internalCache->set('key', 'value');

        $computePromise = async(fn () => $atomicCache->computeIfPresent('key', function (): string {
            return 'new-value';
        }));

        $deletePromise = async(fn () => $atomicCache->delete('key'));

        self::assertSame('new-value', $computePromise->await());
        self::assertTrue($deletePromise->await());
    }
}
