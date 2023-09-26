<?php
/**
 * Plugin Name: sage
 * Version: 1.0.0
 * Plugin URI: http://www.hughlashbrooke.com/
 * Description: This is your starter template for your next WordPress plugin.
 * Author: Hugh Lashbrooke
 * Author URI: http://www.hughlashbrooke.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: sage
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin class files.
require_once __DIR__ . '/includes/class-sage.php';
require_once __DIR__ . '/includes/class-sage-settings.php';

// Load plugin libraries.
require_once __DIR__ . '/includes/lib/class-sage-admin-api.php';
require_once __DIR__ . '/includes/lib/class-sage-post-type.php';
require_once __DIR__ . '/includes/lib/class-sage-taxonomy.php';

/**
 * Returns the main instance of sage to prevent the need to use globals.
 *
 * @return object sage
 * @since  1.0.0
 */
function sage()
{
    $instance = sage::instance(__FILE__, '1.0.0');

    if (is_null($instance->settings)) {
        $instance->settings = sage_Settings::instance($instance);
    }

    return $instance;
}

sage();
