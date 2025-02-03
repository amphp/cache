<?php declare(strict_types=1);

namespace Amp\Cache;

use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Interval;
use function Amp\weakClosure;

/**
 * A cache which stores data in an in-memory (local) array.
 * This class may be used as a least-recently-used (LRU) cache of a given size.
 * Iterating over the cache will iterate from least-recently-used to most-recently-used.
 *
 * @template TValue
 * @implements Cache<TValue>
 * @implements \IteratorAggregate<string, TValue>
 */
final class LocalCache implements Cache, \Countable, \IteratorAggregate
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var array<TValue> */
    private array $cache = [];

    /** @var array<int> */
    private array $timeouts = [];

    private bool $isSortNeeded = false;

    private readonly Interval $gcInterval;

    /** @var int<1, max>|null */
    private readonly ?int $sizeLimit;

    /**
     * @param int<1, max>|null $sizeLimit The maximum size of cache array (number of elements). NULL for unlimited size.
     * @param float $gcInterval The frequency in seconds at which expired cache entries should be garbage collected.
     */
    public function __construct(?int $sizeLimit = null, float $gcInterval = 5)
    {
        if ($sizeLimit !== null && $sizeLimit < 1) {
            throw new \Error('Invalid sizeLimit, must be > 0: ' . $sizeLimit);
        }

        $this->sizeLimit = $sizeLimit;

        $this->gcInterval = new Interval($gcInterval, weakClosure(function (): void {
            $now = \time();

            if ($this->isSortNeeded) {
                \asort($this->timeouts, \SORT_NUMERIC);
                $this->isSortNeeded = false;
            }

            foreach ($this->timeouts as $key => $expiry) {
                if ($now <= $expiry) {
                    break;
                }

                unset(
                    $this->cache[$key],
                    $this->timeouts[$key],
                );
            }
        }), reference: false);
    }

    public function get(string $key): mixed
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        $value = $this->cache[$key];
        unset($this->cache[$key]);

        if (isset($this->timeouts[$key]) && \time() > $this->timeouts[$key]) {
            unset($this->timeouts[$key]);

            return null;
        }

        $this->cache[$key] = $value;

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if ($value === null) {
            throw new CacheException('Cannot store NULL in ' . self::class);
        }

        if ($ttl === null) {
            unset($this->timeouts[$key]);
        } elseif ($ttl >= 0) {
            $expiry = \time() + $ttl;
            $this->timeouts[$key] = $expiry;
            $this->isSortNeeded = true;
        } else {
            throw new \Error("Invalid cache TTL ({$ttl}; integer >= 0 or null required");
        }

        unset($this->cache[$key]);
        if (\count($this->cache) === $this->sizeLimit) {
            /** @var string $keyToEvict */
            $keyToEvict = \array_key_first($this->cache);
            unset($this->cache[$keyToEvict]);
        }

        $this->cache[$key] = $value;
    }

    public function delete(string $key): bool
    {
        $exists = isset($this->cache[$key]);

        unset(
            $this->cache[$key],
            $this->timeouts[$key],
        );

        return $exists;
    }

    public function count(): int
    {
        return \count($this->cache);
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->cache as $key => $value) {
            yield (string) $key => $value;
        }
    }
}
