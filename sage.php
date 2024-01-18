<?php
/**
 * Plugin Name: Sage
 * Version: 1.0.0
 * Plugin URI: https://github.com/Lenny4/SageWordpress
 * Description: A plugin to use Sage on your wordpress website.
 * Author: Alexandre Beaujour
 * Author URI: https://lenny4.github.io/
 * Requires at least: 6.3
 * Tested up to: 6.3
 *
 * Text Domain: sage
 * Domain Path: /lang/
 * @package WordPress
 * @author Alexandre Beaujour
 */

use App\lib\SageGraphQl;
use App\lib\SageWoocommerce;
use App\Sage;
use App\SageSettings;

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Returns the main instance of sage to prevent the need to use globals.
 */
function sage(): Sage
{
    $instance = Sage::instance(__FILE__, '1.0.0');

    if (is_null($instance->settings)) {
        $instance->settings = SageSettings::instance($instance);
    }

    if (is_null($instance->sageGraphQl)) {
        $instance->sageGraphQl = SageGraphQl::instance($instance);
    }

    if (is_null($instance->sageWoocommerce)) {
        $instance->sageWoocommerce = SageWoocommerce::instance($instance);
    }

    return $instance;
}

sage();
