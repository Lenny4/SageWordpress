<?php

namespace App\class;

use WC_Shipping_Method;

class SageShippingMethod__id__ extends WC_Shipping_Method
{
    public function __construct()
    {
        parent::__construct();
        $this->id = 'sage__id__';
        $this->method_title = '__name__';
        $this->method_description = '__description__';
        $this->enabled = "yes";
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];
        $this->init();
    }

    /**
     * Init user set variables.
     */
    public function init()
    {
        // Load the settings API
        $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

        // Save settings in admin if you have any defined
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }
}
