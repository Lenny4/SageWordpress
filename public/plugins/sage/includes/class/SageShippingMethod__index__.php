<?php

namespace App\class;

use WC_Shipping_Free_Shipping;

if (!defined('ABSPATH')) {
    exit;
}

// clone of: woocommerce/includes/shipping/free-shipping/class-wc-shipping-free-shipping.php
class SageShippingMethod__index__ extends WC_Shipping_Free_Shipping
{
    public function __construct($instance_id = 0)
    {
        $this->id = '__id__';
        $this->instance_id = absint($instance_id);
        $this->method_title = '__name__';
        $this->method_description = '__description__';
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
        $this->after_init();
    }

    public function after_init(): void
    {
        $this->instance_form_fields['title']['default'] = $this->method_title;
        $this->instance_form_fields['requires']['title'] = $this->method_title . ' ' . __(' requires', 'sage');
    }

    public static function enqueue_admin_js(): void
    {
        // nothing
    }
}
