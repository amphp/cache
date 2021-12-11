<?php

namespace Amp\Cache;

/**
 * Cache implementation that just ignores all operations and always resolves to `null`.
 */
final class NullCache implements Cache
{
    public function get(string $key): ?string
    {
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = null): void
    {
        // Nothing to do.
    }

    public function delete(string $key): bool
    {
        return false;
    }
}
