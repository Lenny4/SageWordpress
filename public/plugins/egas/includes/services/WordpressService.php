<?php

namespace App\services;

use App\controllers\AdminController;
use App\enum\Sage\TiersTypeEnum;
use App\resources\FArticleResource;
use App\resources\FComptetResource;
use App\Sage;
use App\utils\PathUtils;
use App\Utils\TaxeUtils;
use Automattic\WooCommerce\Utilities\OrderUtil;
use DateTime;
use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Tax;
use WP_Application_Passwords;
use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

class WordpressService
{
    private static ?WordpressService $instance = null;
    public ?array $resources = null;

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
        $this->applyDefaultSageEntityMenuOptions();
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
    private function applyDefaultSageEntityMenuOptions(bool $force = false): void
    {
        $optionNames = [];
        foreach ($this->getResources() as $sageEntityMenu) {
            foreach ($sageEntityMenu->getOptions() as $option) {
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

    /**
     * @return Resource[]
     */
    public function getResources(): array
    {
        if (is_null($this->resources)) {
            /** @var Resource[] $resources */
            $resources = [];
            $files = glob(__DIR__ . '/../resources' . '/*.php');
            foreach ($files as $file) {
                if (str_ends_with($file, '/Resource.php')) {
                    continue;
                }
                $hookClass = 'App\\resources\\' . basename($file, '.php');
                if (class_exists($hookClass)) {
                    $resources[] = $hookClass::getInstance();
                }
            }
            $this->resources = $resources;
        }
        return $this->resources;
    }

    public function addWebsiteSageApi(bool $force = false): bool|string
    {
        // woocommerce/includes/admin/class-wc-admin-meta-boxes.php:134 add_meta_box( 'woocommerce-product-data
        $optionFormSubmitted =
            array_key_exists('settings-updated', $_GET) &&
            array_key_exists('page', $_GET) &&
            $_GET["settings-updated"] === 'true' &&
            $_GET["page"] === Sage::TOKEN . '_settings';
        if (!($force || ($optionFormSubmitted && current_user_can('manage_options')))) {
            return false;
        }

        $applicationPasswordOption = Sage::TOKEN . '_application-passwords';
        $userApplicationPassword = get_option($applicationPasswordOption, null);
        $user_id = get_current_user_id();
        $optionHasPassword = false;
        if (!is_null($userApplicationPassword)) {
            $passwords = WP_Application_Passwords::get_user_application_passwords($userApplicationPassword);
            $optionHasPassword = current(array_filter($passwords, static fn(array $password): bool => $password['name'] === $applicationPasswordOption)) !== false;
        }

        if (
            !$optionHasPassword ||
            !$this->isApiAuthenticated()
        ) {
            $newPassword = $this->createApplicationPassword($user_id, $applicationPasswordOption);
            return $this->createUpdateWebsite($user_id, $newPassword);
        }
        return false;
    }

    private function isApiAuthenticated(): bool
    {
        $response = RequestService::getInstance()->apiRequest('/Website/' . $_SERVER['HTTP_HOST'] . '/Authorization');
        return $response === 'true';
    }

    /**
     * https://developer.wordpress.org/rest-api/reference/application-passwords/#create-a-application-password
     * todo create TU to check if this work with every wordpress version
     */
    private function createApplicationPassword(string $user_id, string $applicationPasswordOption): string
    {
        $passwords = WP_Application_Passwords::get_user_application_passwords($user_id);
        $currentPassword = current(array_filter($passwords, static fn(array $password): bool => $password['name'] === $applicationPasswordOption));
        if ($currentPassword !== false) {
            WP_Application_Passwords::delete_application_password($user_id, $currentPassword["uuid"]);
        }

        $newApplicationPassword = WP_Application_Passwords::create_new_application_password($user_id, [
            'name' => $applicationPasswordOption
        ]);
        $newPassword = $newApplicationPassword[0];
        update_option($applicationPasswordOption, $user_id);
        return $newPassword;
    }

    private function createUpdateWebsite(string $user_id, string $password): bool|string
    {
        $graphqlService = GraphQLService::getInstance();
        $user = get_user_by('id', $user_id);
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
        $graphqlService = GraphQLService::getInstance();
        [$taxe, $rates] = $this->getWordpressTaxes();
        $fTaxes = $graphqlService->getFTaxes(useCache: false, getFromSage: true);
        if (!AdminController::showErrors($fTaxes)) {
            $taxeChanges = $this->getTaxesChanges($fTaxes, $rates);
            $this->applyTaxesChanges($taxeChanges);
            if ($showMessage && $taxeChanges !== []) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?= __("Les taxes Sage ont été mises à jour.", Sage::TOKEN) ?></strong></p>
                </div>
                <?php
            }
        }
    }

    public function getWordpressTaxes(): array
    {
        $taxes = WC_Tax::get_tax_rate_classes();
        $taxe = current(array_filter($taxes, static function (stdClass $taxe) {
            return $taxe->slug === Sage::TOKEN;
        }));
        if ($taxe === false) {
            WC_Tax::create_tax_class(__('Sage', Sage::TOKEN), Sage::TOKEN);
            $taxes = WC_Tax::get_tax_rate_classes();
            $taxe = current(array_filter($taxes, static function (stdClass $taxe) {
                return $taxe->slug === Sage::TOKEN;
            }));
        }
        $rates = WC_Tax::get_rates_for_tax_class($taxe->slug);
        return [$taxe, $rates];
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

    private function applyTaxesChanges(array $taxeChanges): void
    {
        foreach ($taxeChanges as $taxeChange) {
            if ($taxeChange["change"] === TaxeUtils::ADD_TAXE_ACTION) {
                WC_Tax::_insert_tax_rate([
                    "tax_rate_country" => "",
                    "tax_rate_state" => "",
                    "tax_rate" => $taxeChange["new"]->taNp === 1 ? 0 : (string)$taxeChange["new"]->taTaux,
                    "tax_rate_name" => $taxeChange["new"]->taCode,
                    "tax_rate_priority" => "1",
                    "tax_rate_compound" => "0",
                    "tax_rate_shipping" => "1",
                    "tax_rate_class" => Sage::TOKEN
                ]);
            } else if ($taxeChange["change"] === TaxeUtils::REMOVE_TAXE_ACTION) {
                WC_Tax::_delete_tax_rate($taxeChange["old"]->tax_rate_id);
            }
        }
    }

    private function updateShippingMethodsWithSage(): void
    {
        $graphqlService = GraphQLService::getInstance();
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
        // todo register_post_type here
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
            $sageGraphQl = GraphqlService::getInstance();
            $fArticle = $sageGraphQl->getFArticle($arRef, checkIfExists: true);
            $sageGraphQl->updateFArticleFromWebsite($arRef, is_null($fArticle));
            // no need because it's done directly by Sage Api
            // update_post_meta($postId, '_' . Sage::TOKEN . '_updateApi', null);
        }
    }

    public function get_order_screen_id(): string
    {
        // copy of register_order_origin_column in woocommerce/src/Internal/Orders/OrderAttributionController.php
        return OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
    }

    public function saveCustomerUserMetaFields(?int $userId): void
    {
        $nbUpdatedMeta = 0;
        $inSage = (bool)get_option(Sage::TOKEN . '_auto_create_' . Sage::TOKEN . '_fcomptet');
        $ctNum = null;
        $newFComptet = false;
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, '_' . Sage::TOKEN)) {
                $value = trim(preg_replace('/\s\s+/', ' ', $value));
                if ($key === '_' . Sage::TOKEN . '_creationType') {
                    if ($value === 'new') {
                        $newFComptet = true;
                    } else if ($value === 'none') {
                        $inSage = false;
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
        if (!$inSage || $nbUpdatedMeta === 0) {
            return;
        }
        update_user_meta($userId, '_' . Sage::TOKEN . '_updateApi', (new DateTime())->format('Y-m-d H:i:s'));
        [$createdOrUpdatedUserId, $message] = $this->updateUserOrFComptet($ctNum, $userId, newFComptet: $newFComptet);
        if ($newFComptet && is_null($createdOrUpdatedUserId)) {
            $this->deleteSageMetadataForUser($userId);
        }
        if ($message) {
            $redirect = add_query_arg(Sage::TOKEN . '_message', urlencode($message), wp_get_referer());
            wp_redirect($redirect);
            exit;
        }
    }

    /**
     * If fComptet is more up to date than user -> update user in wordpress
     * If user is more up to date than fComptet -> update fComptet in sage
     */
    public function updateUserOrFComptet(
        ?string              $ctNum,
        ?int                 $shouldBeUserId = null,
        stdClass|string|null $fComptet = null,
        bool                 $ignorePingApi = false,
        bool                 $newFComptet = false,
    ): array
    {
        if (is_null($ctNum) && !$newFComptet) {
            return [null, "<div class='error'>
                    " . __("Vous devez spécifier le numéro de compte Sage", Sage::TOKEN) . "
                            </div>"];
        }
        if (is_null($fComptet) && !is_null($ctNum)) {
            $ctNum = str_replace(' ', '', strtoupper($ctNum));
            $fComptet = GraphqlService::getInstance()->getFComptet($ctNum, ignorePingApi: $ignorePingApi);
        }
        if ($newFComptet) {
            if (!is_null($fComptet)) {
                return [null, "<div class='error'>
                    " . __("Vous essayez de créer compte Sage qui existe déjà (" . $ctNum . ")", Sage::TOKEN) . "
                            </div>"];
            }
            if (is_null($shouldBeUserId)) {
                return [null, "<div class='error'>
                    " . __("Vous devez spécifier l'id compte Wordpress", Sage::TOKEN) . "
                            </div>"];
            }
            $fComptet = GraphqlService::getInstance()->createUpdateFComptet(
                userId: $shouldBeUserId,
                ctNum: $ctNum,
                new: true,
                getError: true,
            );
            if (is_string($fComptet)) {
                return [null, "<div class='notice notice-error is-dismissible'>" . $fComptet . "</div>"];
            }
        }
        if (is_null($fComptet)) {
            $word = 'importé';
            if ($newFComptet) {
                $word = 'crée';
            }
            return [null, "<div class='error'>
                    " . __("Le compte Sage n'a pas pu être " . $word, Sage::TOKEN) . "
                            </div>"];
        }
        $canImportFComptet = $this->canUpdateUserOrFComptet($fComptet);
        if (!empty($canImportFComptet)) {
            return [null, null, "<div class='error'>
                        " . implode(' ', $canImportFComptet) . "
                                </div>"];
        }
        $ctNum = $fComptet->ctNum;
        $userId = $this->getUserIdWithCtNum($ctNum);
        if (!is_null($shouldBeUserId)) {
            if (is_null($userId)) {
                $userId = $shouldBeUserId;
            } else if ($userId !== $shouldBeUserId) {
                return [null, "<div class='error'>
                        " . __("Ce numéro de compte Sage est déjà assigné à un utilisateur Wordpress", Sage::TOKEN) . "
                                </div>"];
            }
        }
        [$userId, $userFromSage, $metadata] = WoocommerceService::getInstance()->convertSageUserToWoocommerce(
            $fComptet,
            $userId,
        );
        if (is_string($userFromSage)) {
            return [null, $userFromSage];
        }
        $newUser = is_null($userId);
        if ($newUser) {
            $userId = wp_create_user($userFromSage->user_login, $userFromSage->user_pass, $userFromSage->user_email);
        }
        if ($userId instanceof WP_Error) {
            return [null, "<div class='notice notice-error is-dismissible'>
                                <pre>" . $userId->get_error_code() . "</pre>
                                <pre>" . $userId->get_error_message() . "</pre>
                                </div>"];
        }
        $updateApi = empty(get_user_meta($userId, '_' . Sage::TOKEN . '_updateApi', true));
        if ($updateApi) {
            $wpUser = new WP_User($userId);
            if ($wpUser->user_login !== $userFromSage->user_login) {
                wp_update_user($userFromSage);
            }
            foreach ($metadata as $key => $value) {
                update_user_meta($userId, $key, $value);
            }
        } else {
            if (!$newFComptet) { // no need update fComptet as sageGraphQl->createUpdateFComptet already update fComptet
                $fComptet = GraphqlService::getInstance()->updateFComptetFromWebsite(
                    ctNum: $ctNum,
                    getError: true,
                );
                if (is_string($fComptet)) {
                    return [null, $fComptet];
                }
            }
            // no need because it's done directly by Sage Api
            // update_user_meta($userId, '_' . Sage::TOKEN . '_updateApi', null);
        }

        $url = "<strong><span style='display: block; clear: both;'><a href='" . get_admin_url() . "user-edit.php?user_id=" . $userId . "'>" . __("Voir l'utilisateur", Sage::TOKEN) . "</a></span></strong>";
        if (!$newUser) {
            return [$userId, "<div class='notice notice-success is-dismissible'>
                        " . __('L\'utilisateur a été modifié', Sage::TOKEN) . $url . "
                                </div>"];
        }
        return [$userId, "<div class='notice notice-success is-dismissible'>
                        " . __('L\'utilisateur a été créé', Sage::TOKEN) . $url . "
                                </div>"];
    }

    public function canUpdateUserOrFComptet(stdClass $fComptet): array
    {
        // all fields here must be [IsProjected(false)]
        $result = [];
        if ($fComptet->ctType !== TiersTypeEnum::TiersTypeClient->value) {
            $result[] = __("Le compte " . $fComptet->ctNum . " n'est pas un compte client.", Sage::TOKEN);
        }
        return $result;
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

    public function deleteSageMetadataForUser(int $userId): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("
DELETE
FROM {$wpdb->usermeta}
WHERE user_id = %s AND meta_key LIKE '_" . Sage::TOKEN . "_%'
        ", [$userId]));
    }

    public function getUserWordpressIdForSage(int $userId)
    {
        return get_user_meta($userId, FComptetResource::META_KEY, true);
    }
}
