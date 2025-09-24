<?php

namespace App\controllers;

use App\class\SageExpectedOption;
use App\resources\Resource;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;

class AdminController
{
    public static function registerMenu(): void
    {
        $resources = SageService::getInstance()->getResources();
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
                    'page_title' => __($resource->getTitle(), Sage::TOKEN),
                    'menu_title' => __($resource->getTitle(), Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_' . $resource->getEntityName(),
                    'function' => static function () use ($resource): void {
                        echo $resource->getTitle();
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

    public static function getWrongOptions(): string|null
    {
        $pDossier = GraphqlService::getInstance()->getPDossier();
        $sageExpectedOptions = [
            new SageExpectedOption(
                optionName: 'woocommerce_enable_guest_checkout',
                optionValue: 'no',
                trans: __('Allow customers to place orders without an account', 'woocommerce'),
                description: __("Lorsque cette option est activée vos clients ne sont pas obligés de se connecter à leurs comptes pour passer commande et il est donc impossible de créer automatiquement la commande passé dans Woocommerce dans Sage.", Sage::TOKEN),
            ),
            new SageExpectedOption(
                optionName: 'woocommerce_calc_taxes',
                optionValue: 'yes',
                trans: __('Enable tax rates and calculations', 'woocommerce'),
                description: __("Cette option doit être activé pour que le plugin Sage fonctionne correctement afin de récupérer les taxes directement renseignées dans Sage.", Sage::TOKEN),
            ),
        ];
        if (!is_null($pDossier?->nDeviseCompteNavigation?->dCodeIso)) {
            $sageExpectedOptions[] = new SageExpectedOption(
                optionName: 'woocommerce_currency',
                optionValue: $pDossier->nDeviseCompteNavigation->dCodeIso,
                trans: __('Currency', 'woocommerce'),
                description: __("La devise dans Woocommerce n'est pas la même que dans Sage.", Sage::TOKEN),
            );
        }
        /** @var SageExpectedOption[] $changes */
        $changes = [];
        foreach ($sageExpectedOptions as $sageExpectedOption) {
            $optionName = $sageExpectedOption->getOptionName();
            $expectedOptionValue = $sageExpectedOption->getOptionValue();
            $value = get_option($optionName);
            $sageExpectedOption->setCurrentOptionValue($value);
            if ($value !== $expectedOptionValue) {
                $changes[] = $sageExpectedOption;
            }
        }
        if ($changes !== []) {
            $result = "<div class='error''>";
            $fieldsForm = '';
            $optionNames = [];
            foreach ($changes as $sageExpectedOption) {
                // todo use twig
                $optionValue = $sageExpectedOption->getOptionValue();
                $result .= "<div>" . __('Le plugin Sage a besoin de modifier l\'option', Sage::TOKEN) . " <code>" .
                    $sageExpectedOption->getTrans() . "</code> " . __('pour lui donner la valeur', Sage::TOKEN) . " <code>" .
                    $optionValue . "</code>
<div class='tooltip'>
        <span class='dashicons dashicons-info' style='padding-right: 22px'></span>
        <div class='tooltiptext' style='right: 0'>" . $sageExpectedOption->getDescription() . "</div>
    </div>
</div>";
                $optionName = $sageExpectedOption->getOptionName();
                $fieldsForm .= '<input type="hidden" name="' . $optionName . '" value="' . $optionValue . '">';
                $optionNames[] = $optionName;
            }
            $result .= '<form method="post" action="options.php" enctype="multipart/form-data">'
                . $fieldsForm
                . '<input type="hidden" name="page_options" value="' . esc_attr(implode(',', $optionNames)) . '"/>
                <input type="hidden" name="_wp_http_referer" value="' . esc_attr($_SERVER["REQUEST_URI"]) . '">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="option_page" value="options"/>'
                . wp_nonce_field('options-options', '_wpnonce', true, false)
                . '<p class="submit">
                <input name="Update" type="submit" class="button-primary" value="' . esc_attr(__('Mettre à jour', Sage::TOKEN)) . '">
                </p>
                </form>
                </div>';
            return $result;
        }
        return null;
    }
}
