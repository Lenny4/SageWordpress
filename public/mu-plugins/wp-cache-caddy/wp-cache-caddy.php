<?php
/*
Plugin Name: Caddy Cache Purge
Description: Automatically purges the FrankenPHP/Caddy wp_cache when posts are updated.
*/

add_action('save_post', 'caddy_cache_purge', 10, 3);
add_action('deleted_post', 'caddy_cache_purge');
add_action('trashed_post', 'caddy_cache_purge');

// original https://github.com/StephenMiracle/frankenwp/blob/main/wp-content/mu-plugins/contentCachePurge.php
function caddy_cache_purge($post_ID = null)
{
    static $already_called = false;
    if ($already_called) {
        return;
    }
    $already_called = true;
    $url = home_url() . '/__cache/purge';
    wp_remote_post($url, [
        'method' => 'POST',
        'blocking' => false,
        'timeout' => 2,
        'sslverify' => false, // Set to true in production
        "headers" => [
            "X-WPSidekick-Purge-Key" => "IuIifdYa34Nlj6nZ84yrmevw",
        ]
    ]);
}
