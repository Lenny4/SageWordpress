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
}
