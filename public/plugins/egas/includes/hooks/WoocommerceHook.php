<?php

namespace App\hooks;

use App\class\SageShippingMethod__index__;
use App\controllers\AdminController;
use App\controllers\WoocommerceController;
use App\resources\FArticleResource;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\TwigService;
use App\services\WoocommerceService;
use stdClass;
use Swaggest\JsonDiff\JsonDiff;
use WC_Order;
use WC_Product;
use WC_Shipping_Rate;

class WoocommerceHook
{
    public function __construct()
    {
        // region link wordpress order to sage order
        $screenId = 'woocommerce_page_wc-orders';
        add_action('add_meta_boxes_' . $screenId, static function (WC_Order $order) use ($screenId): void { // woocommerce/src/Internal/Admin/Orders/Edit.php: do_action( 'add_meta_boxes_' . $this->screen_id, $this->order );
            add_meta_box(
                'woocommerce-order-' . Sage::TOKEN . '-main',
                __('Sage', Sage::TOKEN),
                static function () use ($order) {
                    WoocommerceController::getMetaboxSage($order);
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

        add_filter('woocommerce_shipping_rate_cost', static function (string $cost, WC_Shipping_Rate $wcShippingRate) {
            return (string)(WoocommerceService::getInstance()->getShippingRateCosts(WC()->cart, $wcShippingRate) ?? $cost);
        }, accepted_args: 2);
        add_filter('woocommerce_shipping_rate_label', static function (string $label, WC_Shipping_Rate $wcShippingRate) {
            if (!str_starts_with($wcShippingRate->get_method_id(), Sage::TOKEN . '-')) {
                return $label;
            }
            // todo vérifier que c'est bon
            $remove = '[Egas] ';
            if (str_starts_with($label, $remove)) {
                $label = substr($label, strlen($remove));
            }
            return $label;
        }, accepted_args: 2);

        add_filter('product_type_selector', static function (array $types): array {
            $arRef = SageService::getInstance()->getArRef(get_the_ID());
            if (!empty($arRef)) {
                return [Sage::TOKEN => __('Sage', Sage::TOKEN)];
            }
            return array_merge([Sage::TOKEN => __('Sage', Sage::TOKEN)], $types);
        });

        add_action('add_meta_boxes', static function (string $screen, mixed $obj): void { // remove [Product type | virtual | downloadable] add product arRef
            if ($screen === 'product') {
                global $wp_meta_boxes;
                WoocommerceController::showMetaBoxProduct($wp_meta_boxes, $screen);
            } else if ($screen === 'woocommerce_page_wc-orders') {
                global $wp_meta_boxes;
                WoocommerceController::showMetaBoxOrder($wp_meta_boxes, $screen);
            }
        }, 40, 2); // woocommerce/includes/admin/class-wc-admin-meta-boxes.php => 40 > 30 : add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );

        // region Custom Product Tabs In WooCommerce https://aovup.com/woocommerce/add-tabs/
        add_filter('woocommerce_product_data_tabs', static function (array $tabs) { // Code to Create Tab in the Backend
            foreach ($tabs as $tabName => $value) {
                if (!in_array($tabName, [
                    'linked_product',
                    'advanced',
                ])) {
                    $tabs[$tabName]["class"][] = 'hide_if_' . Sage::TOKEN;
                }
            }

            $tabs[Sage::TOKEN] = [
                'label' => __('Sage', Sage::TOKEN),
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
            $oldMetaData = $product->get_meta_data();
            $arRef = $product->get_meta(FArticleResource::META_KEY);
            $meta = [
                'changes' => [],
                'old' => $oldMetaData,
                'new' => $oldMetaData,
            ];
            $responseError = null;
            $updateApi = $product->get_meta('_' . Sage::TOKEN . '_updateApi'); // returns "" if not exists in bdd
            $graphqlService = GraphqlService::getInstance();
            $fArticle = $graphqlService->getFArticle($arRef);
            if (!empty($arRef) && empty($updateApi)) {
                [$response, $responseError, $message, $postId] = WoocommerceService::getInstance()->importFArticleFromSage($arRef, ignoreCanImport: true, fArticle: $fArticle);
                if (is_null($responseError)) {
                    $product->read_meta_data(true);
                    $meta['new'] = $product->get_meta_data();
                    foreach ($meta as $key => $value) {
                        $meta[$key . 'Array'] = new stdClass();
                        foreach ($value as $metaItem) {
                            $data = $metaItem->get_data();
                            if ($data['key'] === '_' . Sage::TOKEN . '_last_update') {
                                continue;
                            }
                            $meta[$key . 'Array']->{$data['key']} = $data['value'];
                        }
                    }
                    $jsonDiff = new JsonDiff($meta['oldArray'], $meta['newArray']);
                    $meta['changes'] = [
                        'removed' => (array)$jsonDiff->getRemoved(),
                        'added' => (array)$jsonDiff->getAdded(),
                        'modified' => (array)$jsonDiff->getModifiedNew(),
                    ];
                }
            }
            $changeTypes = array_keys($meta['changes']);
            foreach ($changeTypes as $type) {
                foreach ($meta['changes'][$type] as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        $meta['changes'][$type][$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                    }
                }
            }
            if (isset($meta["changes"]["removed"])) {
                $meta["changes"]["removed"] = array_filter($meta["changes"]["removed"], static function (string $value) {
                    return !empty($value);
                });
            }
            $hasChanges = false;
            foreach ($changeTypes as $type) {
                if (!empty($meta['changes'][$type])) {
                    $hasChanges = true;
                    break;
                }
            }
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
                'responseError' => $responseError,
                'metaChanges' => $meta['changes'],
                'productMeta' => $meta['new'],
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
        add_filter('woocommerce_shipping_methods', static function (array $result) {
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
                    file_get_contents(__DIR__ . '/class/' . $className . '.php'),
                    $skeletonShippingMethod);
                foreach ($pExpeditions as $i => $pExpedition) {
                    $thisSkeletonShippingMethod = str_replace(
                        ['__index__', '__id__', '__name__', '__description__'],
                        [
                            $i,
                            $pExpedition->slug,
                            '[' . __('Sage', Sage::TOKEN) . '] ' . $pExpedition->eIntitule,
                            '<span style="font-weight: bold">[' . __('Sage', Sage::TOKEN) . ']</span> ' . $pExpedition->eIntitule,
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
        add_action('woocommerce_settings_shipping', static function () {
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
    }
}
