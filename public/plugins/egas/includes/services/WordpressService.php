<?php

namespace App\services;

use App\controllers\AdminController;
use App\resources\FArticleResource;
use App\resources\FComptetResource;
use App\Sage;
use App\utils\PathUtils;
use App\utils\TaxeUtils;
use Automattic\WooCommerce\Utilities\OrderUtil;
use DateTime;
use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WP_Application_Passwords;

if (!defined('ABSPATH')) {
    exit;
}

class WordpressService
{
    private static ?WordpressService $instance = null;

    public function install(): void
    {
        $sage = Sage::getInstance();
        $cacheService = CacheService::getInstance();
        $plugin_data = get_plugin_data($sage->file);
        $version = $plugin_data['Version'];
        update_option(Sage::TOKEN . '_version', $version);
        // region delete FilesystemAdapter cache
        $cacheService->clear();
        // endregion
        // region delete twig cache
        $dir = str_replace(Sage::TOKEN . '.php', 'templates/cache', $sage->file);
        if (is_dir($dir)) {
            $filesystem = new Filesystem();
            $filesystem->remove([$dir]);
        }
        // endregion
        $this->applyDefaultResourceOptions();
        $this->addWebsiteSageApi(true);

        // $this->init() is called during activation and add_action init because sometimes add_action init could fail when plugin is installed
        $this->init();
        flush_rewrite_rules();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * We specifically set the default value in bdd in case between an upgrade we change the default value.
     * This way the user we keep the previous value if he never changed it.
     */
    private function applyDefaultResourceOptions(bool $force = false): void
    {
        $optionNames = [];
        foreach (SageService::getInstance()->getResources() as $resource) {
            foreach ($resource->getOptions() as $option) {
                $optionNames[Sage::TOKEN . '_' . $option['id']] = $option['default'];
            }
        }
        $options = get_options(array_keys($optionNames));
        foreach ($options as $option => $value) {
            if ($force || $value === false) {
                update_option($option, $optionNames[$option]);
            }
        }
    }

    public function addWebsiteSageApi(bool $force = false): bool|string
    {
        // woocommerce/includes/admin/class-wc-admin-meta-boxes.php:134 add_meta_box( 'woocommerce-product-data
        $formSubmitted =
            array_key_exists('settings-updated', $_GET) &&
            array_key_exists('page', $_GET) &&
            $_GET["settings-updated"] === 'true' &&
            $_GET["page"] === Sage::TOKEN . '_settings';
        if (
            !$force && (
                !($formSubmitted && current_user_can('manage_options')) ||
                !$this->isApiAuthenticated()
            )
        ) {
            return false;
        }
        $userId = get_current_user_id();
        $password = $this->getCreateApplicationPassword($userId, $force);
        return $this->createUpdateWebsite($userId, $password);
    }

    private function isApiAuthenticated(): bool
    {
        $response = RequestService::getInstance()->apiRequest('/Website/' . $_SERVER['HTTP_HOST'] . '/authorization');
        return $response === 'true';
    }

    /**
     * https://developer.wordpress.org/rest-api/reference/application-passwords/#create-a-application-password
     */
    private function getCreateApplicationPassword(string $userId, bool $force = false): string
    {
        $optionName = Sage::TOKEN . '_application-passwords';
        $password = get_option($optionName, null);
        $passwords = WP_Application_Passwords::get_user_application_passwords($userId);
        $currentPassword = current(array_filter($passwords, static fn(array $password): bool => $password['name'] === $optionName));
        if ($force || empty($password) || $currentPassword === false) {
            if ($currentPassword !== false) {
                WP_Application_Passwords::delete_application_password($userId, $optionName);
            }
            $newApplicationPassword = WP_Application_Passwords::create_new_application_password($userId, [
                'name' => $optionName
            ]);
            $password = $newApplicationPassword[0];
            update_option($optionName, $password);
        }
        return $password;
    }

    private function createUpdateWebsite(string $userId, string $password): bool|string
    {
        $graphqlService = GraphqlService::getInstance();
        $user = get_user_by('id', $userId);
        $stdClass = $graphqlService->createUpdateWebsite(
            username: $user->data->user_login,
            password: $password,
            getError: true,
        );
        if (is_string($stdClass)) {
            return $stdClass;
        }
        if (is_null($stdClass)) {
            return false;
        }
        update_option(Sage::TOKEN . '_authorization', $stdClass->data->createUpdateWebsite->authorization);
        update_option(Sage::TOKEN . '_website_id', $stdClass->data->createUpdateWebsite->id);

        $graphqlService->updateAllSageEntitiesInOption(ignores: ['getFTaxes']);
        $this->updateTaxes(showMessage: false);
        $this->updateShippingMethodsWithSage();
        AdminController::adminNotices("
<div class='notice notice-success is-dismissible'>
    <p>" . __('Connexion réussie à l\'API. Les paramètres ont été mis à jour.', Sage::TOKEN) . "</p>
</div>
");
        return true;
    }

    public function updateTaxes(bool $showMessage = true): void
    {
        $woocommerceService = WoocommerceService::getInstance();
        [$taxe, $rates] = $woocommerceService->getWordpressTaxes();
        $fTaxes = GraphqlService::getInstance()->getFTaxes(useCache: false, getFromSage: true);
        if (!AdminController::showErrors($fTaxes)) {
            $taxeChanges = $this->getTaxesChanges($fTaxes, $rates);
            $woocommerceService->applyTaxesChanges($taxeChanges);
            if ($showMessage && $taxeChanges !== []) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?= __("Les taxes Sage ont été mises à jour.", Sage::TOKEN) ?></strong></p>
                </div>
                <?php
            }
        }
    }

    private function getTaxesChanges(array $fTaxes, array $rates): array
    {
        $taxeChanges = [];
        $compareFunction = function (stdClass $fTaxe, stdClass $rate) {
            $taTaux = (float)($fTaxe->taNp === 1 ? 0 : $fTaxe->taTaux);
            return
                $fTaxe->taCode === $rate->tax_rate_name &&
                $taTaux === (float)$rate->tax_rate &&
                $rate->tax_rate_country === '' &&
                $rate->postcode_count === 0 &&
                $rate->city_count === 0;
        };
        foreach ($fTaxes as $fTaxe) {
            $rate = current(array_filter($rates, static function (stdClass $rate) use ($compareFunction, $fTaxe) {
                return $compareFunction($fTaxe, $rate);
            }));
            if ($rate === false) {
                $taxeChanges[] = [
                    'old' => null,
                    'new' => $fTaxe,
                    'change' => TaxeUtils::ADD_TAXE_ACTION,
                ];
            }
        }
        foreach ($rates as $rate) {
            $fTaxe = current(array_filter($fTaxes, static function (stdClass $fTaxe) use ($compareFunction, $rate) {
                return $compareFunction($fTaxe, $rate);
            }));
            if ($fTaxe === false) {
                $taxeChanges[] = [
                    'old' => $rate,
                    'new' => null,
                    'change' => TaxeUtils::REMOVE_TAXE_ACTION,
                ];
            }
        }
        return $taxeChanges;
    }

    private function updateShippingMethodsWithSage(): void
    {
        $graphqlService = GraphqlService::getInstance();
        // woocommerce/includes/class-wc-ajax.php : shipping_zone_add_method
        $pExpeditions = $graphqlService->getPExpeditions();
        $newSlugs = array_map(static function (stdClass $pExpedition) {
            return $pExpedition->slug;
        }, $pExpeditions);
        $zones = WC_Shipping_Zones::get_zones();
        $zoneIds = [0, ...array_map(static function (array $zone) {
            return $zone['id'];
        }, $zones)];
        foreach ($zoneIds as $zoneId) {
            $zone = new WC_Shipping_Zone($zoneId);
            $oldSlugs = [];
            foreach ($zone->get_shipping_methods() as $shippingMethod) {
                if (!str_starts_with($shippingMethod->id, Sage::TOKEN . '-')) {
                    continue;
                }
                $oldSlugs[] = $shippingMethod->id;
                if (!in_array($shippingMethod->id, $newSlugs, true)) {
                    $zone->delete_shipping_method($shippingMethod->get_instance_id());
                }
            }
            foreach ($pExpeditions as $pExpedition) {
                if (!in_array($pExpedition->slug, $oldSlugs, true)) {
                    $zone->add_shipping_method($pExpedition->slug);
                }
            }
        }
        update_option(Sage::TOKEN . '_shipping_methods_updated', new DateTime());
    }

    public function init(): void
    {
        // Handle localisation.
        $this->load_plugin_textdomain();
    }

    private function load_plugin_textdomain(): void
    {
        $sage = Sage::getInstance();
        $domain = Sage::TOKEN;
        $locale = apply_filters('plugin_locale', get_locale(), $domain);
        load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, dirname(plugin_basename($sage->file)) . '/lang/');
    }

    public function onSavePost(int $postId): void
    {
        if ($postId === 0) {
            return;
        }
        $arRef = null;
        $flatternPost = PathUtils::flatternPostSageData($_POST);
        foreach ($flatternPost as $key => $value) {
            if (str_starts_with($key, '_' . Sage::TOKEN)) {
                if ($key === FArticleResource::META_KEY) {
                    $arRef = $value;
                }
                update_post_meta($postId, $key, $value);
            }
        }
        if (!empty($arRef)) {
            update_post_meta($postId, '_' . Sage::TOKEN . '_updateApi', (new DateTime())->format('Y-m-d H:i:s'));
            GraphqlService::getInstance()->createUpdateFArticleFromWebsite($arRef);
            // no need because it's done directly by Sage Api
            // update_post_meta($postId, '_' . Sage::TOKEN . '_updateApi', null);
        }
    }

    public function get_order_screen_id(): string
    {
        // copy of register_order_origin_column in woocommerce/src/Internal/Orders/OrderAttributionController.php
        return OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
    }

    public function saveCustomerUserMetaFields(int $userId, bool $new): void
    {
        $nbUpdatedMeta = 0;
        $autoCreateSageFcomptet = (bool)get_option(Sage::TOKEN . '_auto_create_sage_fcomptet');
        $ctNum = null;
        $newFComptet = false;
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, '_' . Sage::TOKEN)) {
                $value = trim(preg_replace('/\s\s+/', ' ', $value));
                if ($key === '_' . Sage::TOKEN . '_creationType') {
                    if ($value === 'new') {
                        $newFComptet = true;
                    } else if ($value === 'none') {
                        $autoCreateSageFcomptet = false;
                    }
                }
                if ($key === FComptetResource::META_KEY) {
                    $value = strtoupper($value);
                    $ctNum = $value;
                }
                $nbUpdatedMeta++;
                update_user_meta($userId, $key, $value);
            }
        }
        if (!$autoCreateSageFcomptet || ($nbUpdatedMeta === 0 && !$new)) {
            return;
        }
        update_user_meta($userId, '_' . Sage::TOKEN . '_updateApi', (new DateTime())->format('Y-m-d H:i:s'));
        [$createdOrUpdatedUserId, $message] = SageService::getInstance()->updateUserOrFComptet($ctNum, $userId, newFComptet: $newFComptet);
        if ($newFComptet && is_null($createdOrUpdatedUserId)) {
            $this->deleteSageMetadataForUser($userId);
        }
        if ($message) {
            $redirect = add_query_arg(Sage::TOKEN . '_message', urlencode($message), wp_get_referer());
            wp_redirect($redirect);
            exit;
        }
    }

    public function deleteSageMetadataForUser(int $userId): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("
DELETE
FROM {$wpdb->usermeta}
WHERE user_id = %s AND meta_key LIKE '_" . Sage::TOKEN . "_%'
        ", [$userId]));
    }

    public function getUserIdWithCtNum(string $ctNum): int|null
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare("
SELECT user_id
FROM {$wpdb->usermeta}
WHERE meta_key = %s
  AND meta_value = %s
", [FComptetResource::META_KEY, $ctNum]));
        if (!empty($r)) {
            return (int)$r[0]->user_id;
        }
        return null;
    }

    public function getUserWordpressIdForSage(int $userId)
    {
        return get_user_meta($userId, FComptetResource::META_KEY, true);
    }

    public function getValidWordpressMail(?string $value): string|null
    {
        if (is_null($value)) {
            return null;
        }
        if (empty($value = trim($value))) {
            return null;
        }
        $emails = explode(';', $value);
        if (!filter_var(($email = trim($emails[0])), FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $email;
    }

    public function deleteMetaTrashResource(string $key, string $value): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("
DELETE
FROM {$wpdb->postmeta}
WHERE {$wpdb->postmeta}.post_id IN (SELECT DISTINCT(postmeta2.post_id)
                              FROM (SELECT * FROM {$wpdb->postmeta}) postmeta2
                                       INNER JOIN {$wpdb->posts}
                                                  ON {$wpdb->posts}.ID = postmeta2.post_id AND {$wpdb->posts}.post_status = 'trash'
                              WHERE meta_key = %s
                                AND meta_value = %s)
  AND {$wpdb->postmeta}.meta_key LIKE '_" . Sage::TOKEN . "_%'
        ", [$key, $value]));
    }
}
