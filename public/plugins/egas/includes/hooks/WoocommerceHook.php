<?php

namespace App\hooks;

use App\controllers\WoocommerceController;
use App\Sage;
use App\services\WoocommerceService;
use WC_Order;
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
    }
}
