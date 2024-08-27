<?php

namespace App\Utils;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderUtils
{
    public const REPLACE_PRODUCT_ACTION = 'replace_product_action';
    public const ADD_PRODUCT_ACTION = 'add_product_action';
    public const REMOVE_PRODUCT_ACTION = 'remove_product_action';
    public const CHANGE_QUANTITY_PRODUCT_ACTION = 'change_quantity_product_action';
    public const CHANGE_PRICE_PRODUCT_ACTION = 'change_price_product_action';
    public const CHANGE_TAXES_PRODUCT_ACTION = 'change_taxes_product_action';

    public const ADD_SHIPPING_ACTION = 'add_shipping_action';
    public const REMOVE_SHIPPING_ACTION = 'remove_shipping_action';

    public const REMOVE_WC_ORDER_ITEM_TAX_ACTION = 'remove_wc_order_item_tax_action';
}
