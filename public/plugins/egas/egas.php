<?php
/**
 * Plugin Name: Egas
 * Version: 1.0.0
 * Plugin URI: https://github.com/Lenny4/SageWordpress
 * Description: A plugin to use Sage on your wordpress website.
 * Author: Alexandre Beaujour
 * Author URI: https://lenny4.github.io/
 * Requires at least: 6.3
 * Tested up to: 6.3
 * Requires Plugins: woocommerce
 *
 * Text Domain: egas
 * Domain Path: /lang/
 * @package WordPress
 * @author Alexandre Beaujour
 */

use App\Sage;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// todo remove for production
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$sage = Sage::getInstance(__FILE__);
if (!$sage->isWooCommerceActive()) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' .
            __('Egas a besoin de Woocommerce pour fonctionner.', Sage::TOKEN) .
            '</p></div>';
    });
} else {
    $sage->registerHooks();
}
