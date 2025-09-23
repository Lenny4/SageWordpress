<?php

namespace App\hooks;

use App\controllers\AdminController;
use App\Sage;
use App\services\GraphqlService;
use App\services\TwigService;
use App\services\WordpressService;
use WP_User;

class WordpressHook
{
    public function __construct()
    {
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
            TwigService::getInstance()->render('data.html.twig');
            if (is_admin() && current_user_can('activate_plugins')) {
                AdminController::adminNotices(
                    "<div id='" . Sage::TOKEN . "_appstate' class='notice notice-info is-dismissible hidden'>
                        <div class='content'></div>
                    </div>"
                    . (array_key_exists(Sage::TOKEN . '_message', $_GET)
                        ? str_replace("\\'", "'", $_GET[Sage::TOKEN . '_message'])
                        : ""
                    )
                );
                AdminController::showWrongOptions();
            }
            $screen_id = WordpressService::getInstance()->get_order_screen_id();
            // like register_order_origin_column in woocommerce/src/Internal/Orders/OrderAttributionController.php
            // HPOS and non-HPOS use different hooks.
            add_filter("manage_{$screen_id}_columns", [AdminController::class, 'addColumn'], 11);
            add_filter("manage_edit-{$screen_id}_columns", [AdminController::class, 'addColumn'], 11);
            add_action("manage_{$screen_id}_custom_column", [AdminController::class, 'displayColumn'], 10, 2);
            add_action("manage_{$screen_id}_posts_custom_column", [AdminController::class, 'displayColumn'], 10, 2);
        });
        // region link wordpress user to sage user
        add_action('personal_options', function (WP_User $user): void {
            $sageGraphQl = GraphQLService::getInstance();
            TwigService::getInstance()->render('user/formMetaFields.html.twig', [
                'user' => $user,
                'userMetaWordpress' => get_user_meta($user->ID),
                'pCattarifs' => $sageGraphQl->getPCattarifs(),
                'pCatComptas' => $sageGraphQl->getPCatComptas(),
            ]);
        });
        add_action('user_new_form', function (): void {
            $sageGraphQl = GraphQLService::getInstance();
            TwigService::getInstance()->render('user/formMetaFields.html.twig', [
                'user' => null,
                'userMetaWordpress' => null,
                'pCattarifs' => $sageGraphQl->getPCattarifs(),
                'pCatComptas' => $sageGraphQl->getPCatComptas(),
            ]);
        });
        add_action('profile_update', function (int $userId) {
            WordpressService::getInstance()->saveCustomerUserMetaFields($userId);
        }, accepted_args: 1);
        add_action('user_register', function (int $userId) {
            WordpressService::getInstance()->saveCustomerUserMetaFields($userId);
        }, accepted_args: 1);
        // endregion
    }
}
