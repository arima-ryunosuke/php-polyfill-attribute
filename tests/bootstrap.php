<?php

use Psr\SimpleCache\CacheInterface;
use ryunosuke\polyfill\attribute\Provider;

require_once __DIR__ . '/../vendor/autoload.php';

Provider::setCacheConfig(new class() implements CacheInterface {
    public function get($key, $default = null)
    {
        return null;
    }

    public function set($key, $value, $ttl = null): bool
    {
        return false;
    }

    public function delete($key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return false;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return false;
    }

    public function deleteMultiple($keys): bool
    {
        return false;
    }

    public function has($key): bool
    {
        return false;
    }
});
