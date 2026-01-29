<?php

namespace App\services;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

if (!defined('ABSPATH')) {
    exit;
}

class CacheService
{
    public final const CACHE_LIFETIME = 3600;

    private static ?CacheService $instance = null;
    private FilesystemAdapter $cache;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->cache = new FilesystemAdapter(defaultLifetime: self::CACHE_LIFETIME);
        }
//        self::$instance->cache->clear();
        return self::$instance;
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->cache->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }
}
