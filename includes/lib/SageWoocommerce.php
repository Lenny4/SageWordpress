<?php

namespace App\lib;

use App\Sage;
use StdClass;

if (!defined('ABSPATH')) {
    exit;
}

final class SageWoocommerce
{
    public static string $metaKey = '_sage_arRef';
    private static ?self $_instance = null;

    private function __construct(public ?Sage $sage)
    {
    }

    public static function instance(Sage $sage): ?self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($sage);
        }

        return self::$_instance;
    }

    public function convertSageArticleToWoocommerce(StdClass|null $fArticle): array|null
    {
        if (is_null($fArticle)) {
            return null;
        }
        return [
            'name' => $fArticle->arDesign,
            'meta_data' => [
                ['key' => self::$metaKey, 'value' => $fArticle->arRef]
            ],
        ];
    }

    public function alreadyExists(string $arRef): bool
    {
        global $wpdb;
        return empty($wpdb->get_results(
            $wpdb->prepare(
                "
SELECT {$wpdb->posts}.ID
FROM {$wpdb->posts}
         INNER JOIN wp_postmeta ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
WHERE {$wpdb->posts}.post_type = 'product'
  AND {$wpdb->postmeta}.meta_key = %s
  AND {$wpdb->postmeta}.meta_value = %s
                        ", [self::$metaKey, $arRef]), OBJECT_K));
    }
}
