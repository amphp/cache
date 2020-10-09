<?php

namespace Amp\Cache\Test;

use Amp\Cache\Cache;
use Amp\Cache\CacheException;
use Amp\Cache\SerializedCache;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\NativeSerializer;
use Amp\Serialization\SerializationException;
use Amp\Serialization\Serializer;

class SerializedCacheTest extends AsyncTestCase
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
    public function testSerializableValue($value)
    {
        $key = 'key';
        $serializer = new NativeSerializer;
        $serializedValue = $serializer->serialize($value);

        $mock = $this->createMock(Cache::class);

        $mock->expects($this->once())
            ->method('set')
            ->with($key, $serializedValue);

        $mock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn($serializedValue);

        $cache = new SerializedCache($mock, $serializer);

        $cache->set('key', $value);

        $this->assertEquals($value, $cache->get('key'));
    }

    public function testSerializerThrowingOnGet()
    {
        $this->expectException(SerializationException::class);

        $serializer = $this->createMock(Serializer::class);
        $serializer->method('unserialize')
            ->willThrowException(new SerializationException);

        $mock = $this->createMock(Cache::class);
        $mock->expects($this->once())
            ->method('get')
            ->with('key')
            ->willReturn('value');

        $cache = new SerializedCache($mock, $serializer);

        $value = $cache->get('key');
    }

    public function testSerializerThrowingOnSet()
    {
        $this->expectException(SerializationException::class);

        $serializer = $this->createMock(Serializer::class);
        $serializer->method('serialize')
            ->willThrowException(new SerializationException);

        $cache = new SerializedCache($this->createMock(Cache::class), $serializer);

        $cache->set('key', 'value');
    }

    public function testStoringNull()
    {
        $this->expectException(CacheException::class);

        $cache = new SerializedCache($this->createMock(Cache::class), new NativeSerializer);

        $cache->set('key', null);
    }
}
