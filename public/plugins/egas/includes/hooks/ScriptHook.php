<?php

namespace App\hooks;

use App\Sage;

class ScriptHook
{
    public function __construct()
    {
        $sage = Sage::getInstance();
        $assetsDistUrl = esc_url(trailingslashit(plugins_url('/dist/', $sage->file)));
        $scriptSuffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        add_action('wp_enqueue_scripts', function () use ($assetsDistUrl, $scriptSuffix, $sage): void {
            $plugin_data = get_plugin_data($sage->file);
            $version = $plugin_data['Version'];
            wp_register_style(Sage::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend.css', [], $version);
            wp_enqueue_style(Sage::TOKEN . '-frontend');
            wp_register_script(Sage::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend' . $scriptSuffix . '.js', ['jquery'], $version, true);
            wp_enqueue_script(Sage::TOKEN . '-frontend');
        }, 10);
        add_action('admin_enqueue_scripts', function () use ($assetsDistUrl, $scriptSuffix, $sage): void {
            $plugin_data = get_plugin_data($sage->file);
            $version = $plugin_data['Version'];
            wp_register_script(Sage::TOKEN . '-admin', esc_url($assetsDistUrl) . 'admin' . $scriptSuffix . '.js', ['jquery'], $version, true);
            wp_enqueue_script(Sage::TOKEN . '-admin');
            wp_register_style(Sage::TOKEN . '-admin', esc_url($assetsDistUrl) . 'admin.css', [], $version);
            wp_enqueue_style(Sage::TOKEN . '-admin');
        }, 10, 1);
    }
}
