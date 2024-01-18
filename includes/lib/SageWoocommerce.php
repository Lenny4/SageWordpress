<?php

namespace App\lib;

use App\Sage;
use StdClass;

if (!defined('ABSPATH')) {
    exit;
}

final class SageWoocommerce
{
    public final const META_KEY = '_' . Sage::TOKEN . '_arRef';

    private static ?self $_instance = null;

    private function __construct(public ?Sage $sage)
    {
        // region edit woocommerce price
        // https://stackoverflow.com/a/45807054/6824121
        // Simple, grouped and external products
        add_filter('woocommerce_product_get_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        add_filter('woocommerce_product_get_regular_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        // Variations
        add_filter('woocommerce_product_variation_get_regular_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        add_filter('woocommerce_product_variation_get_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        // Variable (price range)
//        add_filter('woocommerce_variation_prices_price', fn($price, $variation, $product) => $this->custom_variable_price($price, $variation, $product), 99, 3);
//        add_filter('woocommerce_variation_prices_regular_price', fn($price, $variation, $product) => $this->custom_variable_price($price, $variation, $product), 99, 3);
        // Handling price caching (see explanations at the end)
//        add_filter('woocommerce_get_variation_prices_hash', fn($price_hash, $product, $for_display) => $this->add_price_multiplier_to_variation_prices_hash($price_hash, $product, $for_display), 99, 3);
        // endregion

        // region add column to product list
        add_filter('manage_edit-product_columns', function (array $columns) { // https://stackoverflow.com/a/44702012/6824121
            $columns['sage'] = __('Sage', 'sage'); // todo change css class
            return $columns;
        }, 10, 1);

        add_action('manage_product_posts_custom_column', function (string $column, int $postId) { // https://www.conicsolutions.net/tutorials/woocommerce-how-to-add-custom-columns-on-the-products-list-in-dashboard/
            if ($column === 'sage') {
                $arRef = Sage::getArRef($postId);
                if (!empty($arRef)) {
                    echo '<span class="dashicons dashicons-yes" style="color: green"></span>';
                } else {
                    echo '<span class="dashicons dashicons-no" style="color: red"></span>';
                }
            }
        }, 10, 2);
        // endregion

    }

    private function custom_price($price, $product): float|string
    {
        $arRef = $product->get_meta(self::META_KEY);
        if (empty($arRef)) {
            return $price;
        }
        return (float)$price; // todo
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
                ['key' => self::META_KEY, 'value' => $fArticle->arRef],
                ['key' => '_' . Sage::TOKEN . '_price', 'value' => [
                    '0' => 45,
                    '1' => 46,
                    '5' => 98,
                ]],
            ],
        ];
    }

    public function getWooCommerceId(string $arRef): int|null
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare(
                "
SELECT {$wpdb->posts}.ID
FROM {$wpdb->posts}
         INNER JOIN wp_postmeta ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
WHERE {$wpdb->posts}.post_type = 'product'
  AND {$wpdb->postmeta}.meta_key = %s
  AND {$wpdb->postmeta}.meta_value = %s
                        ", [self::META_KEY, $arRef]));
        if (!empty($r)) {
            return (int)$r[0]->ID;
        }
        return null;
    }
}
