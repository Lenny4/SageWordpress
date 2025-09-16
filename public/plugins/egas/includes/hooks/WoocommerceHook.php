<?php

namespace App\hooks;

use WC_Order;

class WoocommerceHook
{
    public function __construct()
    {
        add_action('woocommerce_new_order', static function (int $orderId, WC_Order $order): void {
            // todo
        }, accepted_args: 2);
    }
}
