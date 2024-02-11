<?php

namespace App\lib;

use App\Sage;
use App\SageSettings;
use DateTime;
use StdClass;
use WC_Meta_Data;
use WC_Product;

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

    private function custom_price(string $price, WC_Product $product): float|string
    {
        $arRef = $product->get_meta(self::META_KEY);
        if (empty($arRef)) {
            return $price;
        }
        /** @var WC_Meta_Data[] $metaDatas */
        $metaDatas = $product->get_meta_data();
        foreach ($metaDatas as $metaData) {
            $data = $metaData->get_data();
            // todo change according to user
            if ($data["key"] !== '_' . Sage::TOKEN . '_max_price') {
                continue;
            }
            $priceData = json_decode($data["value"], true, 512, JSON_THROW_ON_ERROR);
            $price = $priceData["PriceTtc"];
            break;
        }
        return (float)$price;
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

        $prices = json_decode($fArticle->prices, true, 512, JSON_THROW_ON_ERROR);
        usort($prices, static function (array $a, array $b) {
            return $b['PriceTtc'] <=> $a['PriceTtc'];
        });
        return [
            'name' => $fArticle->arDesign,
            'meta_data' => [
                ['key' => self::META_KEY, 'value' => $fArticle->arRef],
                ['key' => '_' . Sage::TOKEN . '_prices', 'value' => $fArticle->prices],
                ['key' => '_' . Sage::TOKEN . '_max_price', 'value' => json_encode($prices[0], JSON_THROW_ON_ERROR)],
                ['key' => '_' . Sage::TOKEN . '_last_update', 'value' => (new DateTime())->format('Y-m-d H:i:s')],
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

    public function populateMetaDatasFArticle(?array $data, array $fields): array|null
    {
        if (empty($data)) {
            return $data;
        }
        $fieldNames = array_map(static function (array $field) {
            return str_replace(SageSettings::PREFIX_META_DATA, '', $field['name']);
        }, array_filter($fields, static function (array $field) {
            return str_starts_with($field['name'], SageSettings::PREFIX_META_DATA);
        }));
        $arRefs = array_map(static function (array $fArticle) {
            return $fArticle['arRef'];
        }, $data["data"]["fArticles"]["items"]);

        global $wpdb;
        $temps = $wpdb->get_results("
SELECT wp_postmeta2.post_id, wp_postmeta2.meta_value, wp_postmeta2.meta_key
FROM wp_postmeta
         LEFT JOIN wp_postmeta wp_postmeta2 ON wp_postmeta2.post_id = wp_postmeta.post_id
WHERE wp_postmeta.meta_value IN ('" . implode("','", $arRefs) . "')
  AND wp_postmeta2.meta_key IN ('" . implode("','", [self::META_KEY, ...$fieldNames]) . "')
ORDER BY wp_postmeta2.meta_value = '" . self::META_KEY . "';
");
        $results = [];
        $mapping = [];
        foreach ($temps as $temp) {
            if ($temp->meta_key === self::META_KEY) {
                $results[$temp->meta_value] = [];
                $mapping[$temp->post_id] = $temp->meta_value;
                continue;
            }
            $results[$mapping[$temp->post_id]][$temp->meta_key] = $temp->meta_value;
        }

        foreach ($data["data"]["fArticles"]["items"] as &$item) {
            foreach ($fieldNames as $fieldName) {
                if (isset($results[$item["arRef"]][$fieldName])) {
                    $item[$fieldName] = $results[$item["arRef"]][$fieldName];
                } else {
                    $item[$fieldName] = '';
                }
            }
        }
        return $data;
    }
}
