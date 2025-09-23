<?php

namespace App\hooks;

use App\Sage;
use App\services\WordpressService;
use WP_Upgrader;

class InstallPluginHook
{
    public function __construct()
    {
        $sage = Sage::getInstance();
        register_activation_hook($sage->file, function () {
            WordpressService::getInstance()->install();
        });
        register_deactivation_hook($sage->file, function () {
            flush_rewrite_rules();
        });
        add_action('upgrader_process_complete', function (WP_Upgrader $wpUpgrader, array $hook_extra): void {
            // https://developer.wordpress.org/reference/hooks/upgrader_process_complete/#parameters
            if (
                array_key_exists('plugins', $hook_extra) &&
                in_array(Sage::TOKEN . '/' . Sage::TOKEN . '.php', $hook_extra['plugins'], true) // todo replace TOKEN by folder and file name
            ) {
                WordpressService::getInstance()->install();
            }
        }, 10, 2);
    }
}
