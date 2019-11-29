<?php

namespace Amp\Cache;

use Amp\File;
use Amp\File\Driver;
use Amp\Loop;
use Amp\Promise;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Lock;
use function Amp\call;

final class FileCache implements Cache
{
    private static function getFilename(string $key): string
    {
        return \hash('sha256', $key) . '.cache';
    }

    private $directory;
    private $mutex;
    private $gcWatcher;

    public function __construct(string $directory, KeyedMutex $mutex)
    {
        $this->directory = $directory = \rtrim($directory, "/\\");
        $this->mutex = $mutex;

        if (!\interface_exists(Driver::class)) {
            throw new \Error(__CLASS__ . ' requires amphp/file to be installed');
        }

        $gcWatcher = static function () use ($directory, $mutex) {
            try {
                $files = yield File\scandir($directory);

                foreach ($files as $file) {
                    if (\strlen($file) !== 70 || !\substr($file, -\strlen('.cache')) === '.cache') {
                        continue;
                    }

                    /** @var Lock $lock */
                    $lock = yield $mutex->acquire($file);

                    try {
                        /** @var File\File $handle */
                        $handle = yield File\open($directory . '/' . $file, 'r');
                        $ttl = yield $handle->read(4);

                        if (\strlen($ttl) !== 4) {
                            yield $handle->close();
                            continue;
                        }

                        $ttl = \unpack('Nttl', $ttl)['ttl'];
                        if ($ttl < \time()) {
                            yield File\unlink($directory . '/' . $file);
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    } finally {
                        $lock->release();
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        };

        // trigger once, so short running scripts also GC and don't grow forever
        Loop::defer($gcWatcher);

        $this->gcWatcher = Loop::repeat(300000, $gcWatcher);
    }

    public function __destruct()
    {
        if ($this->gcWatcher !== null) {
            Loop::cancel($this->gcWatcher);
        }
    }

    public function get(string $key): Promise
    {
        return call(function () use ($key) {
            $filename = $this->getFilename($key);

            /** @var Lock $lock */
            $lock = yield $this->mutex->acquire($filename);

            try {
                $cacheContent = yield File\get($this->directory . '/' . $filename);

                $ttl = \unpack('Nttl', \substr($cacheContent, 0, 4))['ttl'];
                if ($ttl < \time()) {
                    yield File\unlink($this->directory . '/' . $filename);

                    return null;
                }

                return \substr($cacheContent, 4);
            } catch (\Throwable $e) {
                return null;
            } finally {
                $lock->release();
            }
        });
    }

    public function set(string $key, string $value, int $ttl = null): Promise
    {
        if ($ttl < 0) {
            throw new \Error("Invalid cache TTL ({$ttl}); integer >= 0 or null required");
        }

        return call(function () use ($key, $value, $ttl) {
            $filename = $this->getFilename($key);

            /** @var Lock $lock */
            $lock = yield $this->mutex->acquire($filename);

            if ($ttl === null) {
                $ttl = \PHP_INT_MAX;
            } else {
                $ttl = \time() + $ttl;
            }

            $encodedTtl = \pack('N', $ttl);

            try {
                return yield File\put($this->directory . '/' . $filename, $encodedTtl . $value);
            } finally {
                $lock->release();
            }
        });
    }

    public function delete(string $key): Promise
    {
        return call(function () use ($key) {
            $filename = $this->getFilename($key);

            /** @var Lock $lock */
            $lock = yield $this->mutex->acquire($filename);

            try {
                return yield File\unlink($this->directory . '/' . $filename);
            } finally {
                $lock->release();
            }
        });
    }
}
