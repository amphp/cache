<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\Cache\DelegatingSerializedCache;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\NativeSerializer;
use Amp\Success;

class DelegatingSerializedCacheTest extends AsyncTestCase
{
    public function provideSerializableValues(): iterable
    {
        return [
            [new \stdClass],
            [1],
            [3.14],
            ['test'],
        ];
    }

    /**
     * @dataProvider provideSerializableValues
     */
    public function testSerializableValue($value): \Generator
    {
        $key = 'key';
        $serializer = new NativeSerializer;
        $serializedValue = $serializer->serialize($value);

        $mock = $this->createMock(Cache::class);

        $mock->expects($this->once())
            ->method('set')
            ->with($key, $serializedValue)
            ->willReturn(new Success);

        $mock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn(new Success($serializedValue));

        $cache = new DelegatingSerializedCache($mock, $serializer);

        yield $cache->set('key', $value);

        $this->assertEquals($value, yield $cache->get('key'));
    }
}
