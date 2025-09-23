<?php

namespace App\controllers;

use App\resources\Resource;
use App\Sage;
use App\services\WordpressService;

class AdminController
{
    public static function registerMenu(): void
    {
        $resources = WordpressService::getInstance()->getResources();
        $args = apply_filters(
            Sage::TOKEN . '_menu_settings',
            [
                [
                    'location' => 'menu',
                    // Possible settings: options, menu, submenu.
                    'page_title' => __('Sage', Sage::TOKEN),
                    'menu_title' => __('Sage', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_settings',
                    'function' => null,
                    'icon_url' => 'dashicons-rest-api',
                    'position' => 55.5,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __('Settings', Sage::TOKEN),
                    'menu_title' => __('Settings', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_settings',
                    'function' => function (): void {
                        echo 'blabla';
                    },
                    'position' => null,
                ],
                ...array_map(static fn(Resource $resource): array => [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __($resource->title, Sage::TOKEN),
                    'menu_title' => __($resource->title, Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_' . $resource->entityName,
                    'function' => static function () use ($resource): void {
                        echo $resource->title;
                    },
                    'position' => null,
                ], $resources),
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __('À propos', Sage::TOKEN),
                    'menu_title' => __('À propos', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_about',
                    'function' => static function (): void {
                        echo 'about page';
                    },
                    'position' => null,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __('Logs', Sage::TOKEN),
                    'menu_title' => __('Logs', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_log',
                    'function' => static function (): void {
                        echo 'logs page';
                    },
                    'position' => null,
                ],
            ]
        );
        foreach ($args as $arg) {
            // Do nothing if wrong location key is set.
            if (is_array($arg) && isset($arg['location']) && function_exists('add_' . $arg['location'] . '_page')) {
                switch ($arg['location']) {
                    case 'options':
                    case 'submenu':
                        $page = add_submenu_page(
                            $arg['parent_slug'],
                            $arg['page_title'],
                            $arg['menu_title'],
                            $arg['capability'],
                            $arg['menu_slug'],
                            $arg['function'],
                        );
                        break;
                    case 'menu':
                        $page = add_menu_page(
                            $arg['page_title'],
                            $arg['menu_title'],
                            $arg['capability'],
                            $arg['menu_slug'],
                            $arg['function'],
                            $arg['icon_url'],
                            $arg['position'],
                        );
                        break;
                    default:
                        return;
                }
            }
        }
    }

    public static function showErrors(array|null|string $data): bool
    {
        if (is_string($data) || is_null($data)) {
            if (is_string($data) && is_admin() /*on admin page*/) {
                ?>
                <div class="error"><?= $data ?></div>
                <?php
            }
            return true;
        }
        return false;
    }

    public static function adminNotices($message): void
    {
        add_action('admin_notices', static function () use ($message): void {
            echo $message;
        });
    }
}
