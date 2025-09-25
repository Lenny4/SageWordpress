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
        $woocommerceService = WoocommerceService::getInstance();
        [$taxe, $rates] = $woocommerceService->getWordpressTaxes();
        $fTaxes = GraphQLService::getInstance()->getFTaxes(useCache: false, getFromSage: true);
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

    public function addSections()
    {
        $url = parse_url(get_site_url());
        $defaultWordpressUrl = $url["scheme"] . '://' . $url["host"];
        global $wpdb;
        $settings = [
            'api' => [
                'title' => __('Api', Sage::TOKEN),
                'description' => __('These are fairly standard form input fields.', Sage::TOKEN),
                'fields' => [
                    [
                        'id' => 'api_key',
                        'label' => __('Api key', Sage::TOKEN),
                        'description' => __('Can be found here.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => __('XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX', Sage::TOKEN)
                    ],
                    [
                        'id' => 'api_host_url',
                        'label' => __('Api host url', Sage::TOKEN),
                        'description' => __('Can be found here.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => __('https://192.168.0.1', Sage::TOKEN)
                    ],
                    [
                        'id' => 'activate_https_verification_api',
                        'label' => __('Activer Https Api', Sage::TOKEN),
                        'description' => __("Décochez cette case si vous avez l'erreur: cURL error 60: SSL certificate problem: self-signed certificate.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'on'
                    ],
                    [
                        'id' => 'wordpress_host_url',
                        'label' => __('Wordpress host url', Sage::TOKEN),
                        'description' => __('Renseigner l\'url à laquelle l\'API Sage peut contacter l\'API de Wordpress. Modifier C:\Windows\System32\drivers\etc\hosts si nécessaire sur le serveur de l\'API Sage.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => $defaultWordpressUrl,
                        'placeholder' => __($defaultWordpressUrl, Sage::TOKEN)
                    ],
                    [
                        'id' => 'activate_https_verification_wordpress',
                        'label' => __('Activer Https Wordpress', Sage::TOKEN),
                        'description' => __("Décochez cette case si vous avez l'erreur: <br>The SSL connection could not be established, see inner exception.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'on'
                    ],
                    [
                        'id' => 'wordpress_db_host',
                        'label' => __('Wordpress db host', Sage::TOKEN),
                        'description' => __('Renseigner l\'IP à laquelle l\'API Sage peut contacter la base de données de wordpress.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => $wpdb->dbhost,
                        'placeholder' => __($wpdb->dbhost, Sage::TOKEN)
                    ],
                    [
                        'id' => 'wordpress_db_name',
                        'label' => __('Wordpress database name', Sage::TOKEN),
                        'description' => __('Renseigner le nom de la base de données de wordpress.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => $wpdb->dbname,
                        'placeholder' => __($wpdb->dbname, Sage::TOKEN)
                    ],
                    [
                        'id' => 'wordpress_db_username',
                        'label' => __('Wordpress database username', Sage::TOKEN),
                        'description' => __('Renseigner le nom de l\'utilisateur de la base de données de wordpress.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => $wpdb->dbuser,
                        'placeholder' => __($wpdb->dbuser, Sage::TOKEN)
                    ],
                    [
                        'id' => 'wordpress_db_password',
                        'label' => __('Wordpress database password', Sage::TOKEN),
                        'description' => __('Renseigner le mot de passe de la base de données de wordpress.', Sage::TOKEN),
                        'type' => 'text',
                        'default' => $wpdb->dbpassword,
                        'placeholder' => __($wpdb->dbpassword, Sage::TOKEN)
                    ],
                ]
            ],
        ];
        // Check posted/selected tab.
        $current_section = '';
        if (isset($_POST['tab']) && $_POST['tab']) {
            $current_section = $_POST['tab'];
        } elseif (isset($_GET['tab']) && $_GET['tab']) {
            $current_section = $_GET['tab'];
        }
        $sageService = SageService::getInstance();
        foreach (SageService::getInstance()->getResources() as $sageEntityMenu) {
            $fieldOptions = $sageService->getFieldsForEntity($sageEntityMenu);
            $defaultFields = $sageEntityMenu->getDefaultFields();
            $options = [
                [
                    'id' => $sageEntityMenu->getEntityName() . '_show_fields',
                    'label' => __('Champs à montrer', Sage::TOKEN),
                    'description' => __('Veuillez sélectionner les champs à afficher sur le tableau.', Sage::TOKEN),
                    'type' => '2_select_multi',
                    'options' => $fieldOptions,
                    'default' => $defaultFields,
                ],
                [
                    'id' => $sageEntityMenu->getEntityName() . '_perPage',
                    'label' => __('Nombre d\'élément par défaut par page', Sage::TOKEN),
                    'description' => __('Veuillez sélectionner le nombre de lignes à afficher sur le tableau.', Sage::TOKEN),
                    'type' => 'select',
                    'options' => array_combine(Sage::$paginationRange, Sage::$paginationRange),
                    'default' => (string)Sage::$defaultPagination
                ],
                [
                    'id' => $sageEntityMenu->getEntityName() . '_filter_fields',
                    'label' => __('Champs pouvant être filtrés', Sage::TOKEN),
                    'description' => __('Veuillez sélectionner les champs pouvant servir à filter vos résultats.', Sage::TOKEN),
                    'type' => '2_select_multi',
                    'options' => array_filter($fieldOptions, static function (string $key) {
                        return !str_starts_with($key, Sage::PREFIX_META_DATA);
                    }, ARRAY_FILTER_USE_KEY),
                    'default' => array_filter($defaultFields, static function (string $v) {
                        return !str_starts_with($v, Sage::PREFIX_META_DATA);
                    }),
                ],
                ...$sageEntityMenu->getOptions(),
            ];
            $sageEntityMenu->setOptions($options);
            $settings[$sageEntityMenu->getEntityName()] = [
                'title' => __($sageEntityMenu->getTitle(), Sage::TOKEN),
                'description' => $sageEntityMenu->getDescription(),
                'fields' => $options,
            ];
        }

        foreach ($settings as $section => $data) {

            if ($current_section && $current_section !== $section) {
                continue;
            }

            // Add section to page.
            add_settings_section($section, $data['title'], function (array $section) use ($settings): void {
                $html = '<p>' . $settings[$section['id']]['description'] . '</p>' . "\n";
                echo $html;
            }, Sage::TOKEN . '_settings');

            foreach ($data['fields'] as $field) {

                // Validation callback for field.
                $validation = '';
                if (isset($field['callback'])) {
                    $validation = $field['callback'];
                }

                // Register field.
                $option_name = Sage::TOKEN . '_' . $field['id'];
                register_setting(Sage::TOKEN . '_settings', $option_name, $validation);

                // Add field to page.
                add_settings_field(
                    $field['id'],
                    $field['label'],
                    function (...$args): void {
                        AdminController::display_field(...$args);
                    },
                    Sage::TOKEN . '_settings',
                    $section,
                    ['field' => $field, 'prefix' => Sage::TOKEN . '_']
                );
            }

            if (!$current_section) {
                break;
            }
        }
    }
}
