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
        return self::$instance;
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null)
    {
        return $this->cache->get($key, $callback, $beta, $metadata);
    }

    public function delete(string $key)
    {
        return $this->cache->delete($key);
    }
}
