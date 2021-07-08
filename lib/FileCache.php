<?php /** @noinspection PhpUndefinedFunctionInspection */

namespace Amp\Cache;

use Amp\File;
use Amp\File\Driver;
use Amp\Loop;
use Amp\Sync\KeyedMutex;
use function Amp\asyncCallable;

final class FileCache implements Cache
{
    private static function getFilename(string $key): string
    {
        return \hash('sha256', $key) . '.cache';
    }

    private string $directory;

    private KeyedMutex $mutex;

    private string $gcWatcher;

    public function __construct(string $directory, KeyedMutex $mutex)
    {
        $this->directory = $directory = \rtrim($directory, "/\\");
        $this->mutex = $mutex;

        if (!\interface_exists(Driver::class)) {
            throw new \Error(__CLASS__ . ' requires amphp/file to be installed');
        }

        $gcWatcher = asyncCallable(static function () use ($directory, $mutex): void {
            try {
                $files = File\listFiles($directory);

                foreach ($files as $file) {
                    if (\strlen($file) !== 70 || \substr($file, -\strlen('.cache')) !== '.cache') {
                        continue;
                    }

                    $lock = $mutex->acquire($file);

                    try {
                        $handle = File\openFile($directory . '/' . $file, 'r');

                        try {
                            $ttl = $handle->read(4);

                            if ($ttl === null || \strlen($ttl) !== 4) {
                                continue;
                            }
                        } finally {
                            $handle->close();
                        }

                        $ttl = \unpack('Nttl', $ttl)['ttl'];
                        if ($ttl < \time()) {
                            File\deleteFile($directory . '/' . $file);
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
        });

        // trigger once, so short running scripts also GC and don't grow forever
        Loop::defer($gcWatcher);

        $this->gcWatcher = Loop::repeat(300000, $gcWatcher);

        Loop::unreference($this->gcWatcher);
    }

    public function __destruct()
    {
        Loop::cancel($this->gcWatcher);
    }

    /** @inheritdoc */
    public function get(string $key): ?string
    {
        $filename = $this->getFilename($key);

        $lock = $this->mutex->acquire($filename);

        try {
            $cacheContent = File\read($this->directory . '/' . $filename);

            if (\strlen($cacheContent) < 4) {
                return null;
            }

            $ttl = \unpack('Nttl', \substr($cacheContent, 0, 4))['ttl'];
            if ($ttl < \time()) {
                File\deleteFile($this->directory . '/' . $filename);
                return null;
            }

            $value = \substr($cacheContent, 4);

            \assert(\is_string($value));

            return $value;
        } catch (\Throwable $e) {
            return null;
        } finally {
            $lock->release();
        }
    }

    /** @inheritdoc */
    public function set(string $key, string $value, int $ttl = null): void
    {
        if ($ttl < 0) {
            throw new \Error("Invalid cache TTL ({$ttl}); integer >= 0 or null required");
        }

        $filename = $this->getFilename($key);

        $lock = $this->mutex->acquire($filename);

        if ($ttl === null) {
            $ttl = \PHP_INT_MAX;
        } else {
            $ttl = \time() + $ttl;
        }

        $encodedTtl = \pack('N', $ttl);

        try {
            File\write($this->directory . '/' . $filename, $encodedTtl . $value);
        } finally {
            $lock->release();
        }
    }

    /** @inheritdoc */
    public function delete(string $key): ?bool
    {
        $filename = $this->getFilename($key);

        $lock = $this->mutex->acquire($filename);

        try {
            File\deleteFile($this->directory . '/' . $filename);
        } finally {
            $lock->release();
        }

        return null;
    }
}
