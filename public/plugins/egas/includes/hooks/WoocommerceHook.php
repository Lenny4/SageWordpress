<?php

namespace App\hooks;

use App\class\SageShippingMethod__index__;
use App\class\term\WC_Product_Egas;
use App\controllers\AdminController;
use App\controllers\WoocommerceController;
use App\resources\FArticleResource;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\TwigService;
use App\services\WoocommerceService;
use App\utils\SageTranslationUtils;
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery;
use stdClass;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;
use WC_Shipping_Rate;

class WoocommerceHook
{
    private array $trans;

    public function __construct()
    {
        add_action('init', function (): void {
            $this->trans = SageTranslationUtils::getTranslations();
        });
        // region link wordpress order to sage order
        $screenId = 'woocommerce_page_wc-orders';
        add_action('add_meta_boxes_' . $screenId, static function (WC_Order $order) use ($screenId): void { // woocommerce/src/Internal/Admin/Orders/Edit.php: do_action( 'add_meta_boxes_' . $this->screen_id, $this->order );
            add_meta_box(
                'woocommerce-order-' . Sage::TOKEN . '-main',
                __('Egas', Sage::TOKEN),
                static function () use ($order): void {
                    echo WoocommerceController::getMetaboxFDocentete($order);
                },
                $screenId,
                'normal',
                'high'
            );
        });
        // action is trigger when click update button on order
        add_action('woocommerce_process_shop_order_meta', static function (int $orderId, WC_Order $order): void {
            if ($order->get_status() === 'auto-draft') {
                // handle by the add_action `woocommerce_new_order`
                return;
            }
            WoocommerceService::getInstance()->afterCreateOrEditOrder($order);
        }, accepted_args: 2);
        add_action('woocommerce_new_order', static function (int $orderId, WC_Order $order): void {
            WoocommerceService::getInstance()->afterCreateOrEditOrder($order, true);
        }, accepted_args: 2);
        // endregion
        // todo woocommerce_payment_complete
//        add_action('woocommerce_payment_complete', function ($null, $transactionId): void {
//        }, accepted_args: 2);
        add_filter('woocommerce_orders_table_query_sql', function (string $sql, OrdersTableQuery $table, array $args) {
            return preg_replace(
                "/IN\s*\(\s*('_billing_address_index'\s*,\s*'_shipping_address_index')\s*\)/",
                "IN ($1, '_" . Sage::TOKEN . "_doPiece')",
                $sql
            );
        }, accepted_args: 3);;

        add_filter('woocommerce_shipping_rate_cost', static fn(string $cost, WC_Shipping_Rate $wcShippingRate): string => (string)(WoocommerceService::getInstance()->getShippingRateCosts(WC()->cart, $wcShippingRate) ?? $cost), accepted_args: 2);
        add_filter('woocommerce_shipping_rate_label', static function (string $label, WC_Shipping_Rate $wcShippingRate): string {
            if (!str_starts_with($wcShippingRate->get_method_id(), Sage::TOKEN . '-')) {
                return $label;
            }
            $remove = '[Egas] ';
            if (str_starts_with($label, $remove)) {
                $label = substr($label, strlen($remove));
            }
            return $label;
        }, accepted_args: 2);

        // region Custom Product Tabs In WooCommerce https://aovup.com/woocommerce/add-tabs/
        add_action('add_meta_boxes', static function (string $screen, mixed $obj): void { // remove [Product type | virtual | downloadable] add product arRef
            if ($screen === 'product') {
                global $wp_meta_boxes;
                WoocommerceController::showMetaBoxProduct($wp_meta_boxes, $screen);
            } elseif ($screen === 'woocommerce_page_wc-orders') {
                global $wp_meta_boxes;
                WoocommerceController::showMetaBoxOrder($wp_meta_boxes, $screen);
            }
        }, 40, 2); // woocommerce/includes/admin/class-wc-admin-meta-boxes.php => 40 > 30 : add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
        add_filter('product_type_selector', static function (array $types): array {
            $arRef = get_post_meta(get_the_ID(), FArticleResource::META_KEY, true);
            if (!empty($arRef)) {
                return [Sage::TOKEN => __('Egas', Sage::TOKEN)];
            }
            return array_merge([Sage::TOKEN => __('Sage product', Sage::TOKEN)], $types);
        });
        add_filter('product_type_options', function (array $productOptions): array {
            foreach ($productOptions as &$productOption) {
                $productOption["wrapper_class"] .= ' hide_if_' . Sage::TOKEN;
            }
            return $productOptions;
        });
        add_filter('woocommerce_product_class', function (string $classname, string $product_type): string {
            if ($product_type === Sage::TOKEN) {
                return WC_Product_Egas::class;
            }
            return $classname;
        }, accepted_args: 2);
        add_filter('woocommerce_product_data_tabs', static function (array $tabs) { // Code to Create Tab in the Backend
            foreach (array_keys($tabs) as $tabName) {
                if (!in_array($tabName, [
                    'linked_product',
                    'advanced',
                ])) {
                    $tabs[$tabName]["class"][] = 'hide_if_' . Sage::TOKEN;
                }
            }

            $tabs[Sage::TOKEN] = [
                'label' => __('Egas', Sage::TOKEN),
                'target' => Sage::TARGET_PANEL,
                'class' => ['show_if_' . Sage::TOKEN],
                'priority' => 0,
            ];
            return $tabs;
        });

        add_action('woocommerce_product_data_panels', static function (): void { // Code to Add Data Panel to the Tab
            $product = wc_get_product();
            if (!($product instanceof WC_Product)) {
                return;
            }
            $sageService = SageService::getInstance();
            [
                $fArticle,
                $messages,
                $meta,
                $updateApi,
                $hasChanges,
                $changeTypes
            ] = $sageService->importFromSageIfUpdateApi($sageService->getResource(FArticleResource::ENTITY_NAME), $product->get_id());
            $graphqlService = GraphqlService::getInstance();
            echo TwigService::getInstance()->render('woocommerce/tabs/sage.html.twig', [
                'fArticle' => $fArticle,
                'pCattarifs' => $graphqlService->getPCattarifs(),
                'fCatalogues' => $graphqlService->getFCatalogues(),
                'pCatComptas' => $graphqlService->getPCatComptas(),
                'fFamilles' => $graphqlService->getFFamilles(),
                'pUnites' => $graphqlService->getPUnites(),
                'fDepots' => $graphqlService->getFDepots(),
                'fPays' => $graphqlService->getFPays(),
                'pPreference' => $graphqlService->getPPreference(),
                'panelId' => Sage::TARGET_PANEL,
                'messages' => $messages,
                'meta' => $meta,
                'updateApi' => $updateApi,
                'hasChanges' => $hasChanges,
                'changeTypes' => $changeTypes,
            ]);
        });
        // endregion

        // region taxes
        // woocommerce/includes/admin/settings/views/html-settings-tax.php
        // woocommerce/includes/admin/views/html-admin-settings.php
        add_action('woocommerce_sections_tax', static function (): void {
            WoocommerceService::getInstance()->updateTaxes();
            if (array_key_exists('section', $_GET) && $_GET['section'] === Sage::TOKEN) {
                ?>
                <div class="notice notice-info">
                    <p>
                        <?= __("Veuillez ne pas modifier les taxes Sage manuellement ici, elles sont automatiquement mises à jour en fonction des taxes dans Sage ('Stucture' -> 'Comptabilité' -> 'Taux de taxes').", Sage::TOKEN) ?>
                    </p>
                </div>
                <?php
            }
        });
        // endregion

        // region add sage shipping methods
        add_filter('woocommerce_shipping_methods', static function (array $result): array {
            $className = pathinfo(str_replace('\\', '/', SageShippingMethod__index__::class), PATHINFO_FILENAME);
            $pExpeditions = GraphqlService::getInstance()->getPExpeditions(
                getError: true,
            );
            if (AdminController::showErrors($pExpeditions)) {
                return $result;
            }
            if (
                $pExpeditions !== [] &&
                !class_exists(str_replace('__index__', '0', $className))
            ) {
                preg_match(
                    '/class ' . $className . '[\s\S]*/',
                    file_get_contents(__DIR__ . '/../class/' . $className . '.php'),
                    $skeletonShippingMethod);
                foreach ($pExpeditions as $i => $pExpedition) {
                    $thisSkeletonShippingMethod = str_replace(
                        ['__index__', '__id__', '__name__', '__description__'],
                        [
                            $i,
                            $pExpedition->slug,
                            '[' . __('Egas', Sage::TOKEN) . '] ' . $pExpedition->eIntitule,
                            '<span style="font-weight: bold">[' . __('Egas', Sage::TOKEN) . ']</span> ' . $pExpedition->eIntitule,
                        ],
                        $skeletonShippingMethod[0]
                    );
                    eval(str_replace('@TOKEN@', Sage::TOKEN, $thisSkeletonShippingMethod));
                }
            }
            foreach ($pExpeditions as $i => $pExpedition) {
                $result[$pExpedition->slug] = str_replace('__index__', $i, $className);
            }
            return $result;
        });
        add_action('woocommerce_settings_shipping', static function (): void {
            global $wpdb;
            $r = $wpdb->get_results(
                $wpdb->prepare("
SELECT COUNT(instance_id) nbInstance
FROM {$wpdb->prefix}woocommerce_shipping_zone_methods
WHERE method_id NOT LIKE '" . Sage::TOKEN . "%'
  AND is_enabled = 1
"));
            if ((int)$r[0]->nbInstance > 0) {
                echo '
<div class="notice notice-warning"><p>
    <span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">
        ' . __('Certain Mode(s) d’expédition qui ne proviennent pas de Sage sont activés. Cliquez sur "Désactiver" pour désactiver les modes d\'expéditions qui ne proviennent pas de Sage', Sage::TOKEN) . '
    </span>
    <strong>
    <span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">
        <a href="' . get_site_url() . '/index.php?rest_route=' . urlencode('/' . Sage::TOKEN . '/v1/deactivate-shipping-zones') . '&_wpnonce=' . wp_create_nonce('wp_rest') . '">
        ' . __('Désactiver', Sage::TOKEN) . '
        </a>
    </span>
    </strong>
</p></div>
                ';
            }
        });
        // endregion

        // region edit woocommerce price
        // https://stackoverflow.com/a/45807054/6824121
        add_filter('woocommerce_get_price_including_tax', fn($price, $quantity, $product): float|string => WoocommerceService::getInstance()->custom_price($price, $product, get_current_user_id(), true), 99, 3);
        add_filter('woocommerce_get_price_excluding_tax', fn($price, $quantity, $product): float|string => WoocommerceService::getInstance()->custom_price($price, $product, get_current_user_id(), false), 99, 3);
        // Simple, grouped and external products
        add_filter('woocommerce_product_get_price', fn($price, $product): float|string => WoocommerceService::getInstance()->custom_price($price, $product, get_current_user_id()), 99, 2);
        add_filter('woocommerce_product_get_regular_price', fn($price, $product): float|string => WoocommerceService::getInstance()->custom_price($price, $product, get_current_user_id()), 99, 2);
        // Variations
        add_filter('woocommerce_product_variation_get_regular_price', fn($price, $product): float|string => WoocommerceService::getInstance()->custom_price($price, $product, get_current_user_id()), 99, 2);
        add_filter('woocommerce_product_variation_get_price', fn($price, $product): float|string => WoocommerceService::getInstance()->custom_price($price, $product, get_current_user_id()), 99, 2);
        // Variable (price range)
//        add_filter('woocommerce_variation_prices_price', fn($price, $variation, $product) => $this->custom_variable_price($price, $variation, $product), 99, 3);
//        add_filter('woocommerce_variation_prices_regular_price', fn($price, $variation, $product) => $this->custom_variable_price($price, $variation, $product), 99, 3);
        // Handling price caching (see explanations at the end)
//        add_filter('woocommerce_get_variation_prices_hash', fn($price_hash, $product, $for_display) => $this->add_price_multiplier_to_variation_prices_hash($price_hash, $product, $for_display), 99, 3);
        // endregion

        // region edit woocommerce product display
        add_action('woocommerce_after_order_itemmeta', function (int $item_id, WC_Order_Item $item, WC_Product|bool|null $product): void {
            if (
                is_bool($product) ||
                is_null($product) ||
                !($item instanceof WC_Order_Item_Product)
            ) {
                return;
            }
            $arRef = SageService::getInstance()->get_post_meta_single($product->get_id(), FArticleResource::META_KEY, true);
            if (!empty($arRef)) {
                echo __('Sage ref', Sage::TOKEN) . ': ' . $arRef;
            }
        }, 10, 3);
        // endregion

        // region add column to product list
        add_filter('manage_edit-product_columns', function (array $columns) { // https://stackoverflow.com/a/44702012/6824121
            $columns[Sage::TOKEN] = __('Sage', Sage::TOKEN);
            return $columns;
        }, 10, 1);

        add_action('manage_product_posts_custom_column', function (string $column, int $postId): void { // https://www.conicsolutions.net/tutorials/woocommerce-how-to-add-custom-columns-on-the-products-list-in-dashboard/
            if ($column === Sage::TOKEN) {
                $arRef = get_post_meta($postId, FArticleResource::META_KEY, true);
                if (!empty($arRef)) {
//                    echo '<span class="dashicons dashicons-yes" style="color: green"></span>';
                    echo $arRef;
                } else {
                    echo '<span class="dashicons dashicons-no" style="color: red"></span>';
                }
            }
        }, 10, 2);
        // endregion

        add_filter('woocommerce_order_item_display_meta_key', fn(string $key): string|array => SageTranslationUtils::trans($this->trans, 'words', $key));
        add_filter('woocommerce_order_item_display_meta_value', function (string $value, WC_Meta_Data $wcMetaData) {
            $data = $wcMetaData->get_data();
            if ($data['key'] === '_' . Sage::TOKEN . '_fLotseriesOut' && $data['value'] !== 'null') {
                $data = json_decode((string)$data['value'], true, 512, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_array($data) && !empty($data)) {
                    return $data[0]['lsNoSerie'];
                }
            }
            return $value;
        }, accepted_args: 3);

        add_filter('manage_edit-product_cat_columns', function ($columns) {
            // Ajouter la colonne 'Egas' après le nom
            $columns[Sage::TOKEN] = __("Sage", Sage::TOKEN);
            return $columns;
        });
        add_action('manage_product_cat_custom_column', function ($content, $column_name, $term_id) {
            if (Sage::TOKEN === $column_name) {
                $clNo = get_term_meta($term_id, '_' . Sage::TOKEN . '_clNo', true);
                echo $clNo ?
                    '
<div style="display: inline-block;" data-tippy-content="<div>
<strong>fCatalogue: ' . $clNo . '</strong>
</div>">
  <span class="dashicons dashicons-yes" style="color: green"></span>
</div>
'
                    :
                    '<span class="dashicons dashicons-no" style="color: red"></span>';
            }
        }, 10, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', fn(array $metaDatas): array => array_filter($metaDatas, fn(stdClass $metaData): bool => $metaData->value !== 'null'));
    }
}
