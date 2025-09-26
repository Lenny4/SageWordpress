<?php

namespace App\hooks;

use App\Sage;

class ScriptHook
{
    private ?string $assetsDistUrl = null;
    private ?string $version = null;

    public function __construct()
    {
        add_action('wp_enqueue_scripts', function (): void {
            [$assetsDistUrl, $version] = $this->getData();
            wp_register_style(Sage::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend.css', [], $version);
            wp_enqueue_style(Sage::TOKEN . '-frontend');
            wp_register_script(Sage::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend.js', ['jquery'], $version, true);
            wp_enqueue_script(Sage::TOKEN . '-frontend');

            wp_register_style(Sage::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend.css', [], $version);
            wp_enqueue_style(Sage::TOKEN . '-frontend');
            wp_register_script(Sage::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend.js', ['jquery'], $version, true);
            wp_enqueue_script(Sage::TOKEN . '-frontend');
        });
        add_action('admin_enqueue_scripts', function (): void {
            [$assetsDistUrl, $version] = $this->getData();
            wp_register_script(Sage::TOKEN . '-admin', esc_url($assetsDistUrl) . 'admin.js', ['jquery'], $version, true);
            wp_enqueue_script(Sage::TOKEN . '-admin');
            wp_register_style(Sage::TOKEN . '-admin', esc_url($assetsDistUrl) . 'admin.css', [], $version);
            wp_enqueue_style(Sage::TOKEN . '-admin');
        });
    }

    private function getData(): array
    {
        if (is_null($this->assetsDistUrl)) {
            $sage = Sage::getInstance();
            $this->assetsDistUrl = esc_url(trailingslashit(plugins_url('/dist/', $sage->file)));
            $pluginData = get_plugin_data($sage->file);
            $this->version = $pluginData['Version'];
        }
        return [$this->assetsDistUrl, $this->version];
    }
}
