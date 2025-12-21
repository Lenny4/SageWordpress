<?php

namespace App\hooks;

use App\controllers\AdminController;
use App\controllers\WoocommerceController;
use App\resources\FComptetResource;
use App\Sage;
use App\services\GraphqlService;
use App\services\TwigService;
use App\services\WordpressService;
use stdClass;
use WC_Order;
use WP_REST_Request;
use WP_User;

class WordpressHook
{
    public function __construct()
    {
        $sage = Sage::getInstance();
        add_action('init', function (): void {
            // $this->init() is called during activation and add_action init because sometimes add_action init could fail when plugin is installed
            WordpressService::getInstance()->init();
        }, 0);
        add_action('admin_menu', function (): void {
            AdminController::registerMenu();
        });
        add_action('save_post', function (int $postId = 0): void {
            WordpressService::getInstance()->onSavePost($postId);
        });
        add_action('admin_init', static function (): void {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (str_contains($accept, 'application/json')) {
                return;
            }
            $wordpressService = WordpressService::getInstance();
            $resultAddWebsiteSageApi = $wordpressService->addWebsiteSageApi();
            echo TwigService::getInstance()->render('data.html.twig');
            if (is_string($resultAddWebsiteSageApi)) {
                AdminController::adminNotices("
<div class='error'>
    <p>" . $resultAddWebsiteSageApi . "</p>
</div>
");
            }
            AdminController::adminNotices(
                "<div id='" . Sage::TOKEN . "_appstate' class='notice notice-info is-dismissible hidden'>
                        <div class='content'></div>
                    </div>"
                . (array_key_exists(Sage::TOKEN . '_message', $_GET)
                    ? str_replace("\\'", "'", $_GET[Sage::TOKEN . '_message'])
                    : ""
                )
            );
            if (!is_null($wrongOptions = AdminController::getWrongOptions())) {
                echo $wrongOptions;
            }
            AdminController::addSections();
            $screen_id = $wordpressService->get_order_screen_id();
            // like register_order_origin_column in woocommerce/src/Internal/Orders/OrderAttributionController.php
            // HPOS and non-HPOS use different hooks.
            add_filter("manage_{$screen_id}_columns", [WoocommerceController::class, 'addColumn'], 11);
            add_filter("manage_edit-{$screen_id}_columns", [WoocommerceController::class, 'addColumn'], 11);
            add_action("manage_{$screen_id}_custom_column", static function (string $column_name, WC_Order $order) {
                echo WoocommerceController::displayColumn($column_name, $order);
            }, 10, 2);
            add_action("manage_{$screen_id}_posts_custom_column", static function (string $column_name, WC_Order $order) {
                echo WoocommerceController::displayColumn($column_name, $order);
            }, 10, 2);
        });
        // region link wordpress user to sage user
        add_action('personal_options', function (WP_User $user): void {
            $sageGraphQl = GraphqlService::getInstance();
            echo TwigService::getInstance()->render('user/formMetaFields.html.twig', [
                'user' => $user,
                'userMetaWordpress' => get_user_meta($user->ID),
                'pCattarifs' => $sageGraphQl->getPCattarifs(),
                'pCatComptas' => $sageGraphQl->getPCatComptas(),
            ]);
        });
        add_action('user_new_form', function (): void {
            $sageGraphQl = GraphqlService::getInstance();
            echo TwigService::getInstance()->render('user/formMetaFields.html.twig', [
                'user' => null,
                'userMetaWordpress' => null,
                'pCattarifs' => $sageGraphQl->getPCattarifs(),
                'pCatComptas' => $sageGraphQl->getPCatComptas(),
            ]);
        });
        add_action('profile_update', function (int $userId) {
            WordpressService::getInstance()->saveCustomerUserMetaFields($userId, false);
        });
        add_action('user_register', function (int $userId) {
            WordpressService::getInstance()->saveCustomerUserMetaFields($userId, true);
        });
        // endregion
        // Add settings link to plugins page.
        add_filter('plugin_action_links_' . plugin_basename($sage->file), static function (array $links): array {
            $links[] = '<a href="options-general.php?page=' . Sage::TOKEN . '_settings">' . __('Settings', Sage::TOKEN) . '</a>';
            return $links;
        }
        );
        // Configure placement of plugin settings page. See readme for implementation.
        add_filter(Sage::TOKEN . '_menu_settings', static fn(array $settings = []): array => $settings);
        // region user
        // region user save meta with API: https://wordpress.stackexchange.com/a/422521/201039
        $userMetaProp = Sage::PREFIX_META_DATA;
        add_filter('rest_pre_insert_user', static function ( // /!\ aussi trigger lorsque l'on update un user
            stdClass        $prepared_user,
            WP_REST_Request $request
        ) use ($userMetaProp): stdClass {
            if (!empty($request['meta'])) {
                $prepared_user->{$userMetaProp} = [];
                $ctNum = null;
                foreach ($request['meta'] as $key => $value) {
                    if ($key === FComptetResource::META_KEY) {
                        $ctNum = $value;
                    }
                    $prepared_user->{$userMetaProp}[$key] = $value;
                }
                if (!is_null($ctNum)) {
                    global $wpdb;
                    $r = $wpdb->get_results(
                        $wpdb->prepare("
SELECT {$wpdb->users}.ID, {$wpdb->users}.user_login
FROM {$wpdb->usermeta}
    INNER JOIN {$wpdb->users} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id
WHERE meta_key = %s
  AND meta_value = %s
", [FComptetResource::META_KEY, $ctNum]));
                    if (
                        !empty($r) &&
                        (
                            !property_exists($prepared_user, 'ID') ||
                            (int)$r[0]->ID !== $prepared_user->ID
                        )
                    ) {
                        wp_send_json_error([
                            'existing_user_ctNum' => __("Le compte Sage [" . $ctNum . "] est déjà lié au compte Wordpress [" . $r[0]->user_login . " (id: " . $r[0]->ID . ")]"),
                        ]);
                    }
                }
            }
            return $prepared_user;
        }, accepted_args: 2);
        add_filter('insert_custom_user_meta', static function (
            array   $custom_meta,
            WP_User $user,
            bool    $update,
            array   $userdata
        ) use ($userMetaProp): array {
            if (array_key_exists($userMetaProp, $userdata)) {
                foreach ($userdata[$userMetaProp] as $key => $value) {
                    $custom_meta[$key] = $value;
                }
            }
            return $custom_meta;
        }, accepted_args: 4);
        add_action('rest_after_insert_user', static function (
            WP_User         $user,
            WP_REST_Request $request,
            bool            $creating
        ): void {
            if ($creating) {
                if (get_option(Sage::TOKEN . '_auto_send_mail_import_' . Sage::TOKEN . '_fcomptet')) {
                    // Accepts only 'user', 'admin' , 'both' or default '' as $notify.
                    wp_send_new_user_notifications($user->ID, 'user');
                }
            }
        }, accepted_args: 3);
        // endregion
        // region user show Sage id: https://wordpress.stackexchange.com/a/160423/201039
        add_filter('manage_users_columns', static function (array $columns): array {
            $columns[Sage::TOKEN] = __("Sage", Sage::TOKEN);
            return $columns;
        });
        add_filter('manage_users_custom_column', static function (string $val, string $columnName, int $userId): string {
            return WordpressService::getInstance()->getUserWordpressIdForSage($userId) ?? '';
        }, accepted_args: 3);
        // endregion
        // endregion
    }
}
