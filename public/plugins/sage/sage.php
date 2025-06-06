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

/**
 * Returns the main instance of sage to prevent the need to use globals.
 */
function sage(): Sage
{
    return Sage::instance(__FILE__, '1.0.0');
}

sage();
