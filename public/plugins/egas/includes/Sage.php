<?php

namespace App;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
class Sage
{
    public final const TOKEN = 'egas';
    public final const PREFIX_META_DATA = 'metaData';
    public const META_DATA_PREFIX = self::PREFIX_META_DATA . '_' . Sage::TOKEN;

    public static array $paginationRange = [20, 50, 100];
    private static ?Sage $instance = null;

    private function __construct(public ?string $file = '')
    {
    }

    public static function getInstance(string $file = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($file);
        }
        return self::$instance;
    }

    public function isWooCommerceActive(): bool
    {
        return is_plugin_active('woocommerce/woocommerce.php');
    }

    public function registerHooks(): void
    {
        $files = glob(__DIR__ . '/hooks' . '/*.php');
        foreach ($files as $file) {
            $hookClass = 'App\\hooks\\' . basename($file, '.php');
            if (class_exists($hookClass)) {
                new $hookClass();
            }
        }
    }
}
