<?php
/**
 * This file runs when the plugin in uninstalled (deleted).
 * This will not run when the plugin is deactivated.
 * Ideally you will add all your clean-up scripts here
 * that will clean-up unused meta, options, etc. in the database.
 *
 * @package WordPress Plugin Template/Uninstall
 */

// If plugin is not being uninstalled, exit (do nothing).
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Do something here if plugin is being uninstalled.

// region delete custom post type

//$customPosts = get_posts([
//    'post_type' => 'sage',
//    'numberposts' => -1,
//]);
//foreach ($customPosts as $customPost) {
//    wp_delete_post($customPost->ID, true);
//}

//global $wpdb;
//$wpdb->query("DELETE FROM wp_posts WHERE post_type = 'sage'");
//$wpdb->query("DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT id FROM wp_posts)");
//$wpdb->query("DELETE FROM wp_term_relationships WHERE post_id NOT IN (SELECT id FROM wp_posts)");

// endregion
