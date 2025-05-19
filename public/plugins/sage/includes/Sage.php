<?php

namespace App;

use App\class\SageEntityMenu;
use App\class\SageExpectedOption;
use App\enum\Sage\TiersTypeEnum;
use App\lib\SageAdminApi;
use App\lib\SageGraphQl;
use App\lib\SagePostType;
use App\lib\SageRequest;
use App\lib\SageTaxonomy;
use App\lib\SageWoocommerce;
use App\Utils\FDocenteteUtils;
use App\Utils\SageTranslationUtils;
use Automattic\WooCommerce\Admin\Overrides\Order;
use DateTime;
use StdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WC_Order;
use WC_Product;
use WC_Shipping_Rate;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Upgrader;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
final class Sage
{
    public final const TOKEN = 'sage';

    public final const CACHE_LIFETIME = 3600;
    public final const META_KEY_AR_REF = '_' . self::TOKEN . '_arRef';
    public final const META_KEY_CT_NUM = '_' . self::TOKEN . '_ctNum';
    public final const META_KEY_IDENTIFIER = '_' . self::TOKEN . '_identifier';

    /**
     * The single instance of sage.
     */
    private static ?self $_instance = null;

    /**
     * Local instance of SageAdminApi
     */
    public ?SageAdminApi $admin = null;

    /**
     * Settings class SageSettings
     */
    public SageSettings|null $settings = null;

    /**
     * The main plugin directory.
     */
    public ?string $dir = null;

    /**
     * The plugin assets directory.
     */
    public ?string $assets_dir = null;

    /**
     * The plugin assets URL.
     */
    public ?string $assets_url = null;
    public ?string $assets_dist_url = null;

    /**
     * Suffix for JavaScripts.
     */
    public ?string $script_suffix = null;

    public ?Environment $twig = null;

    public SageGraphQl|null $sageGraphQl = null;

    public SageWoocommerce|null $sageWoocommerce = null;

    public FilesystemAdapter $cache;

    /**
     * Constructor funtion.
     *
     * @param string|null $file File constructor.
     * @param string|null $_version Plugin version.
     */
    public function __construct(public ?string $file = '', public ?string $_version = '1.0.0')
    {
        $dir = dirname($this->file);
        $this->dir = $dir;

        $this->cache = new FilesystemAdapter(defaultLifetime: self::CACHE_LIFETIME);

        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        $this->assets_dist_url = esc_url(trailingslashit(plugins_url('/dist/', $this->file)));

        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        register_activation_hook($this->file, function (): void {
            $this->install();
        });

        register_deactivation_hook($this->file, static function (): void {
            flush_rewrite_rules();
        });

        add_action('upgrader_process_complete', function (WP_Upgrader $wpUpgrader, array $hook_extra): void {
            // https://developer.wordpress.org/reference/hooks/upgrader_process_complete/#parameters
            if (
                array_key_exists('plugins', $hook_extra) &&
                in_array('sage/sage.php', $hook_extra['plugins'], true)
            ) {
                $this->install();
            }
        }, 10, 2);

        // region enqueue js && css
        // Load frontend JS & CSS.
        add_action('wp_enqueue_scripts', function (): void {
            wp_register_style(self::TOKEN . '-frontend', esc_url($this->assets_dist_url) . 'frontend.css', [], $this->_version);
            wp_enqueue_style(self::TOKEN . '-frontend');
            wp_register_script(self::TOKEN . '-frontend', esc_url($this->assets_dist_url) . 'frontend' . $this->script_suffix . '.js', ['jquery'], $this->_version, true);
            wp_enqueue_script(self::TOKEN . '-frontend');
        }, 10);

        // Load admin JS & CSS.
        add_action('admin_enqueue_scripts', function (string $hook = ''): void {
            wp_register_script(self::TOKEN . '-admin', esc_url($this->assets_dist_url) . 'admin' . $this->script_suffix . '.js', ['jquery'], $this->_version, true);
            wp_enqueue_script(self::TOKEN . '-admin');
            wp_register_style(self::TOKEN . '-admin', esc_url($this->assets_dist_url) . 'admin.css', [], $this->_version);
            wp_enqueue_style(self::TOKEN . '-admin');
        }, 10, 1);
        // endregion

        // Load API for generic admin functions.
        if (is_admin()) {
            $this->admin = new SageAdminApi($this);
        }

        add_action('init', function (): void {
            // $this->init() is called during activation and add_action init because sometimes add_action init could fail when plugin is installed
            $this->init();
        }, 0);

        $sage = $this;

        add_action('admin_init', static function () use ($sage): void {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (str_contains($accept, 'application/json')) {
                return;
            }
            echo $sage->twig->render('data.html.twig');
            // like register_order_origin_column in woocommerce/src/Internal/Orders/OrderAttributionController.php
            $sage->settings->registerOrderSageColumn();

            if (is_admin() && current_user_can('activate_plugins')) {
                $allPlugins = get_plugins();
                $pluginId = 'woocommerce/woocommerce.php';
                $isWooCommerceInstalled = array_key_exists($pluginId, $allPlugins);
                add_action('admin_notices', static function () use ($isWooCommerceInstalled, $pluginId, $sage): void {
                    ?>
                    <div id="<?= self::TOKEN ?>_appstate" class="notice notice-info is-dismissible hidden">
                        <div class="content"></div>
                    </div>
                    <?php
                    if (!$isWooCommerceInstalled) {
                        ?>
                        <div class="error"><p>
                                <?= __('Le plugin Sage a besoin que WooCommerce soit installé pour fonctionner.', 'sage') ?>
                            </p></div>
                        <?php
                    } else {
                        if (!is_plugin_active($pluginId)) {
                            ?>
                            <div class="error"><p>
                                <?= __('Le plugin Sage a besoin que WooCommerce soit activé pour fonctionner.', 'sage') ?>
                            </p>
                            </div><?php
                        } else {
                            $sage->showWrongOptions();
                        }
                    }
                    if (array_key_exists(self::TOKEN . '_message', $_GET)) {
                        echo str_replace("\'", "'", $_GET[self::TOKEN . '_message']);
                    }
                    ?>
                    <?php
                });
            }
        });
        // region link wordpress user to sage user
        add_action('personal_options', function (WP_User $user): void {
            $this->addCustomerMetaFields($user);
        });
        add_action('user_new_form', function (): void {
            $this->addCustomerMetaFields();
        });
        add_action('profile_update', function (int $userId) {
            $this->saveCustomerUserMetaFields($userId);
        }, accepted_args: 1);
        add_action('user_register', function (int $userId) {
            $this->saveCustomerUserMetaFields($userId);
        }, accepted_args: 1);
        // endregion

        $this->sageGraphQl = SageGraphQl::instance($this);
        $this->sageWoocommerce = SageWoocommerce::instance($this);
        $this->settings = SageSettings::instance($this);

        $sageGraphQl = $this->sageGraphQl;
        $sageWoocommerce = $this->sageWoocommerce;
        $settings = $this->settings;

        // region twig
        $templatesDir = __DIR__ . '/../templates';
        $filesystemLoader = new FilesystemLoader($templatesDir);
        $twigOptions = [
            'debug' => WP_DEBUG,
        ];
        if (!WP_DEBUG) {
            $twigOptions['cache'] = $templatesDir . '/cache';
        }

        $this->twig = new Environment($filesystemLoader, $twigOptions);
        if (WP_DEBUG) {
            // https://twig.symfony.com/doc/3.x/functions/dump.html
            $this->twig->addExtension(new DebugExtension());
        }

        $this->twig->addFilter(new TwigFilter('trans', static fn(string $string) => __($string, self::TOKEN)));
        $this->twig->addFilter(new TwigFilter('esc_attr', static fn(string $string) => esc_attr($string)));
        $this->twig->addFilter(new TwigFilter('selected', static fn(bool $selected) => selected($selected, true, false)));
        $this->twig->addFilter(new TwigFilter('disabled', static fn(bool $disabled) => disabled($disabled, true, false)));
        $this->twig->addFilter(new TwigFilter('bytesToString', static fn(array $bytes): string => implode('', array_map("chr", $bytes))));
        $this->twig->addFilter(new TwigFilter('wp_nonce_field', static fn(string $action) => wp_nonce_field($action)));
        $this->twig->addFilter(new TwigFilter('wp_create_nonce', static fn(string $action) => wp_create_nonce($action)));
        $this->twig->addFunction(new TwigFunction('getTranslations', static fn(): array => SageTranslationUtils::getTranslations()));
        $this->twig->addFunction(new TwigFunction('get_locale', static fn(): string => substr(get_locale(), 0, 2)));
        $this->twig->addFunction(new TwigFunction('getAllFilterType', static function (): array {
            $r = [];
            foreach ([
                         'StringOperationFilterInput',
                         'IntOperationFilterInput',
                         'ShortOperationFilterInput',
                         'DecimalOperationFilterInput',
                         'DateTimeOperationFilterInput',
                         'UuidOperationFilterInput',
                     ] as $f) {
                switch ($f) {
                    case 'StringOperationFilterInput':
                        $r[$f] = [
                            'contains',
                            'endsWith',
                            'eq',
                            'in',
                            'ncontains',
                            'nendsWith',
                            'neq',
                            'nin',
                            'nstartsWith',
                            'startsWith',
                        ];
                        break;
                    case 'IntOperationFilterInput':
                    case 'ShortOperationFilterInput':
                    case 'DecimalOperationFilterInput':
                    case 'DateTimeOperationFilterInput':
                    case 'UuidOperationFilterInput':
                        $r[$f] = [
                            'eq',
                            'gt',
                            'gte',
                            'in',
                            'lt',
                            'lte',
                            'neq',
                            'ngt',
                            'ngte',
                            'nin',
                            'nlt',
                            'nlte',
                        ];
                        break;
                }
            }

            return $r;
        }));
        $this->twig->addFilter(new TwigFilter('sortByFields', static function (array $item, array $fields): array {
            uksort($item, static function (string $a, string $b) use ($fields): int {
                $fieldsOrder = [];
                foreach ($fields as $i => $f) {
                    $fieldsOrder[str_replace(SageSettings::PREFIX_META_DATA, '', $f['name'])] = $i;
                }

                return $fieldsOrder[$a] <=> $fieldsOrder[$b];
            });
            return $item;
        }));
        $this->twig->addFunction(new TwigFunction('getPaginationRange', static fn(): array => SageSettings::$paginationRange));
        $this->twig->addFunction(new TwigFunction('get_site_url', static fn() => get_site_url()));
        $this->twig->addFunction(new TwigFunction('getUrlWithParam', static function (string $paramName, int|string $v): string|array|null {
            $url = $_SERVER['REQUEST_URI'];
            if (str_contains($url, $paramName)) {
                $url = preg_replace('/' . $paramName . '=([^&]*)/', $paramName . '=' . $v, $url);
            } else {
                $url .= '&' . $paramName . '=' . $v;
            }

            return $url;
        }));
        $this->twig->addFilter(new TwigFilter('json_decode', static fn(string $string): mixed => json_decode(stripslashes($string), true, 512, JSON_THROW_ON_ERROR)));
        $this->twig->addFilter(new TwigFilter('gettype', static fn(mixed $value): string => gettype($value)));
        $this->twig->addFilter(new TwigFilter('removeFields', static fn(array $fields, array $hideFields): array => array_values(array_filter($fields, static fn(array $field): bool => !in_array($field["name"], $hideFields)))));
        $this->twig->addFunction(new TwigFunction('getSortData', static function (array $queryParams): array {
            [$sortField, $sortValue] = SageGraphQl::getSortField($queryParams);

            if ($sortValue === 'asc') {
                $otherSort = 'desc';
            } else {
                $sortValue = 'desc';
                $otherSort = 'asc';
            }

            return [
                'sortValue' => $sortValue,
                'otherSort' => $otherSort,
                'sortField' => $sortField,
            ];
        }));
        $this->twig->addFilter(new TwigFilter('sortInsensitive', static function (array $array): array {
            uasort($array, 'strnatcasecmp');
            return $array;
        }));
        $this->twig->addFunction(new TwigFunction('file_exists', static fn(string $path): bool => file_exists($dir . '/' . $path)));
        $this->twig->addFilter(new TwigFilter('getEntityIdentifier', static function (array $obj, array $mandatoryFields): string {
            $r = [];
            foreach ($mandatoryFields as $mandatoryField) {
                $r[] = $obj[str_replace(SageSettings::PREFIX_META_DATA, '', $mandatoryField)];
            }

            return implode('|', $r);
        }));
        $this->twig->addFunction(new TwigFunction('get_option', static fn(string $option): string => get_option($option)));
        $this->twig->addFunction(new TwigFunction('getPricesProduct', static function (WC_Product $product) use ($sageWoocommerce): array {
            $r = $sageWoocommerce->getPricesProduct($product);
            foreach ($r as &$r1) {
                foreach ($r1 as &$r2) {
                    $r2 = (array)$r2;
                }
            }
            return $r;
        }));
        $this->twig->addFunction(new TwigFunction('get_woocommerce_currency_symbol', static function (): string {
            return html_entity_decode(get_woocommerce_currency_symbol());
        }));
        $this->twig->addFunction(new TwigFunction('order_get_currency', static function (): string {
            return html_entity_decode(get_woocommerce_currency_symbol());
        }));
        $this->twig->addFunction(new TwigFunction('show_taxes_change', static function (array $taxes): string {
            return implode(' | ', array_map(static function (array $taxe) {
                return $taxe['code'] . ' => ' . $taxe['amount'];
            }, $taxes));
        }));
        $this->twig->addFunction(new TwigFunction('getDoTypes', static function (array $fDoclignes): array {
            $result = [];
            foreach ($fDoclignes as $fDocligne) {
                $result[$fDocligne->doType] = '';
                foreach (FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE as $doType => $field) {
                    if (!empty($fDocligne->{'dlPiece' . $field})) {
                        $result[$doType] = '';
                    }
                }
            }
            $result = array_keys($result);
            sort($result);
            return $result;
        }));
        $this->twig->addFunction(new TwigFunction('formatFDoclignes', static function (array $fDoclignes, array $doTypes): array {
            usort($fDoclignes, static function (stdClass $a, stdClass $b) use ($doTypes) {
                foreach ($doTypes as $doType) {
                    if ($a->doType === $doType) {
                        $doPieceA = $a->doPiece;
                    } else {
                        $doPieceA = $a->{'dlPiece' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                    }
                    if ($b->doType === $doType) {
                        $doPieceB = $b->doPiece;
                    } else {
                        $doPieceB = $b->{'dlPiece' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                    }
                    if ($doPieceA !== $doPieceB) {
                        return strcmp($doPieceB, $doPieceA);
                    }
                }
                return 0;
            });
            $nbFDoclignes = count($fDoclignes);
            foreach ($fDoclignes as $fDocligne) {
                $fDocligne->display = [];
                foreach ($doTypes as $doType) {
                    if ($fDocligne->doType === $doType) {
                        $doPiece = $fDocligne->doPiece;
                        $dlQte = (int)$fDocligne->dlQte;
                    } else {
                        $doPiece = $fDocligne->{'dlPiece' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                        $dlQte = (int)$fDocligne->{'dlQte' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                    }
                    $fDocligne->display[$doType] = [
                        'doPiece' => $doPiece,
                        'doType' => $doType,
                        'dlQte' => $dlQte,
                        'prevDoPiece' => '',
                        'nextDoPiece' => '',
                    ];
                }
            }
            foreach ($doTypes as $indexDoType => $doType) {
                foreach ($fDoclignes as $i => $fDocligne) {
                    foreach (['prev' => -1, 'next' => +1] as $f => $v) {
                        $y = $i + $v;
                        while (
                            (
                                ($y > 0 && $v === -1) ||
                                ($y < $nbFDoclignes - 1 && $v === 1)
                            ) &&
                            (
                                $fDoclignes[$y]->display[$doType]['doPiece'] === ''
                            )
                        ) {
                            $y += $v;
                        }
                        if ($i !== $y && $y >= 0 && $y < $nbFDoclignes) {
                            $fDocligne->display[$doType][$f . 'DoPiece'] = $fDoclignes[$y]->display[$doType]['doPiece'];
                        }
                    }
                    $doPiece = $fDocligne->display[$doType]["doPiece"];
                    $prevDoPiece = $fDocligne->display[$doType]["prevDoPiece"];
                    $nextDoPiece = $fDocligne->display[$doType]["nextDoPiece"];
                    $fDocligne->display[$doType]['showBorderBottom'] = $doPiece !== '' && $doPiece !== $nextDoPiece;
                    $fDocligne->display[$doType]['showBorderX'] = $doPiece !== '' || $prevDoPiece === $nextDoPiece;
                    $fDocligne->display[$doType]['showDoPiece'] = !empty($doPiece) && ($doPiece !== $prevDoPiece);
                    $fDocligne->display[$doType]['showArrow'] =
                        $indexDoType > 0 &&
                        $doPiece !== '' &&
                        array_key_exists($doTypes[$indexDoType - 1], $fDocligne->display) &&
                        $fDocligne->display[$doTypes[$indexDoType - 1]]["doPiece"] !== '';
                }
            }

            return $fDoclignes;
        }));
        $this->twig->addFunction(new TwigFunction('getProductChangeLabel', static function (stdClass $productChange, array $products) {
            if (!array_key_exists($productChange->postId, $products)) {
                if (!empty($productChange->fDocligneLabel)) {
                    return $productChange->fDocligneLabel;
                }
                return 'undefined';
            }
            /** @var WC_Product $p */
            $p = $products[$productChange->postId];
            return $p->get_name();
        }));
        $this->twig->addExtension(new IntlExtension());
        $this->twig->addFunction(new TwigFunction('flattenAllTranslations', static function (array $allTranslations): array {
            $flatten = function (array $values, array &$result = []) use (&$flatten) {
                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        $flatten($value, $result);
                    } else {
                        $result[$key] = $value;
                    }
                }
                return $result;
            };
            foreach ($allTranslations as $key => $allTranslation) {
                if (
                    is_array($allTranslation) &&
                    array_key_exists('values', $allTranslation) &&
                    is_array($allTranslation['values'])
                ) {
                    $allTranslations[$key]['values'] = $flatten($allTranslation['values']);
                }
            }

            return $allTranslations;
        }));
        $this->twig->addFunction(new TwigFunction('getFilterInput', static function (array $fields, string $prop) {
            foreach ($fields as $field) {
                if ($field['name'] === $prop) {
                    return $field['type'];
                }
            }
            return null;
        }));
        $this->twig->addFilter(new TwigFilter('wpDate', static function (string $date): string {
            return date_i18n(wc_date_format(), strtotime($date)) . ' ' . date_i18n(wc_time_format(), strtotime($date));
        }));
        $this->twig->addFunction(new TwigFunction('get_admin_url', static function (): string {
            return get_admin_url();
        }));
        $this->twig->addFunction(new TwigFunction('getDefaultFilters', static function () use ($settings): array {
            return array_map(static function (SageEntityMenu $sageEntityMenu) {
                $entityName = $sageEntityMenu->getEntityName();
                return [
                    'entityName' => self::TOKEN . '_' . $entityName,
                    'value' => get_option(self::TOKEN . '_default_filter_' . $entityName, null),
                ];
            }, $settings->sageEntityMenus);
        }));
        $this->twig->addFunction(new TwigFunction('getFDoclignes', static function (array|null|string $fDocentetes) use ($sageWoocommerce): array {
            return $sageWoocommerce->getFDoclignes($fDocentetes);
        }));
        $this->twig->addFunction(new TwigFunction('getMainFDocenteteOfExtendedFDocentetes', static function (WC_Order $order, array|null|string $fDocentetes) use ($sageWoocommerce): stdClass|null|string {
            return $sageWoocommerce->getMainFDocenteteOfExtendedFDocentetes($order, $fDocentetes);
        }));
        $this->twig->addFunction(new TwigFunction('getFDocentete', static function (array $fDocentetes, string $doPiece, int $doType) use ($sageWoocommerce): stdClass|null|string {
            $fDocentete = current(array_filter($fDocentetes, static function (stdClass $fDocentete) use ($doPiece, $doType) {
                return $fDocentete->doPiece === $doPiece && $fDocentete->doType === $doType;
            }));
            if ($fDocentete !== false) {
                return $fDocentete;
            }
            return null;
        }));
        $this->twig->addFunction(new TwigFunction('canUpdateUserOrFComptet', static function (array $fComptet) use($sage): array {
            return $sage->canUpdateUserOrFComptet(json_decode(json_encode($fComptet), false));
        }));
        $this->twig->addFunction(new TwigFunction('canImportFArticle', static function (array $fArticle) use($sageWoocommerce): array {
            return $sageWoocommerce->canImportFArticle(json_decode(json_encode($fArticle), false));
        }));
        $this->twig->addFunction(new TwigFunction('canImportOrderFromSage', static function (array $fDocentete) use($sageWoocommerce): array {
            return $sageWoocommerce->canImportOrderFromSage(json_decode(json_encode($fDocentete), false));
        }));
        // endregion

        // region link wordpress order to sage order
        $screenId = 'woocommerce_page_wc-orders';
        add_action('add_meta_boxes_' . $screenId, static function (WC_Order $order) use ($screenId, $sageWoocommerce): void { // woocommerce/src/Internal/Admin/Orders/Edit.php: do_action( 'add_meta_boxes_' . $this->screen_id, $this->order );
            add_meta_box(
                'woocommerce-order-' . self::TOKEN . '-main',
                __('Sage', 'sage'),
                static function () use ($order, $sageWoocommerce) {
                    echo $sageWoocommerce->getMetaboxSage($order);
                },
                $screenId,
                'normal',
                'high'
            );
        });
        // action is trigger when click update button on order
        add_action('woocommerce_process_shop_order_meta', static function (int $orderId, WC_Order $order) use ($sage): void {
            if ($order->get_status() === 'auto-draft') {
                // handle by the add_action `woocommerce_new_order`
                return;
            }
            $sage->afterCreateOrEditOrder($order);
        }, accepted_args: 2);
        add_action('woocommerce_new_order', static function (int $orderId, WC_Order $order) use ($sage): void {
            $sage->afterCreateOrEditOrder($order, true);
        }, accepted_args: 2);
        // endregion

        // region api endpoint
        add_action('rest_api_init', static function () use ($sageWoocommerce, $sageGraphQl, $settings) {
            register_rest_route(self::TOKEN . '/v1', '/orders/(?P<id>\d+)/sync', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($sageWoocommerce) {
                    $order = new WC_Order($request['id']);
                    $fDocenteteIdentifier = $sageWoocommerce->getFDocenteteIdentifierFromOrder($order);
                    $extendedFDocentetes = $sageWoocommerce->sage->sageGraphQl->getFDocentetes(
                        $fDocenteteIdentifier["doPiece"],
                        [$fDocenteteIdentifier["doType"]],
                        doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                        doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                        getError: true,
                        ignorePingApi: true,
                        getFDoclignes: true,
                        getExpedition: true,
                        addWordpressProductId: true,
                        getUser: true,
                        getLivraison: true,
                        extended: true,
                    );
                    $tasksSynchronizeOrder = $sageWoocommerce->getTasksSynchronizeOrder($order, $extendedFDocentetes);
                    [$message, $order] = $sageWoocommerce->applyTasksSynchronizeOrder($order, $tasksSynchronizeOrder);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => $sageWoocommerce->getMetaboxSage($order, ignorePingApi: true, message: $message)
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/farticle/(?P<arRef>([^&]*))/import', args: [ // https://stackoverflow.com/a/10126995/6824121
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($sageWoocommerce) {
                    $arRef = $request['arRef'];
                    $headers = [];
                    if (!empty($authorization = $request->get_header('authorization'))) {
                        $headers['authorization'] = $authorization;
                    }
                    [$response, $responseError, $message, $postId] = $sageWoocommerce->importFArticleFromSage(
                        $arRef,
                        ignorePingApi: true,
                        headers: $headers,
                    );
                    if ($request->get_param('json') === '1') {
                        $body = $response["body"];
                        try {
                            $body = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
                        } catch (Throwable) {
                            // nothing
                        }
                        return new WP_REST_Response($body, $response['response']['code']);
                    }
                    $order = new Order($request['orderId']);
                    return new WP_REST_Response([
                        'html' => $sageWoocommerce->getMetaboxSage(
                            $order,
                            ignorePingApi: true,
                            message: $message,
                        )
                    ], $response['response']['code']);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/fdocentetes/(?P<doPiece>[A-Za-z0-9]+$)', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($sageGraphQl) {
                    $extended = false;
                    if (
                        array_key_exists('extended', $_GET) &&
                        ($_GET['extended'] === '1' || $_GET['extended'] === 'true')
                    ) {
                        $extended = true;
                    }
                    $fDocentetes = $sageGraphQl->getFDocentetes(
                        strtoupper(trim($request['doPiece'])),
                        doTypes: FDocenteteUtils::DO_TYPE_MAPPABLE,
                        doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                        doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                        getError: true,
                        ignorePingApi: true,
                        getWordpressIds: true,
                        extended: $extended,
                    );
                    if (is_string($fDocentetes)) {
                        return new WP_REST_Response([
                            'message' => $fDocentetes
                        ], 400);
                    }
                    if (is_null($fDocentetes)) {
                        return new WP_REST_Response([
                            'message' => 'Unknown error'
                        ], 500);
                    }
                    if ($fDocentetes === []) {
                        return new WP_REST_Response(null, 404);
                    }
                    return new WP_REST_Response($fDocentetes, Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/orders/(?P<id>\d+)/desynchronize', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($sageWoocommerce) {
                    $order = new WC_Order($request['id']);
                    $order = $sageWoocommerce->desynchronizeOrder($order);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => $sageWoocommerce->getMetaboxSage($order, ignorePingApi: true)
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/orders/(?P<id>\d+)/fdocentete', [
                'methods' => 'POST',
                'callback' => static function (WP_REST_Request $request) use ($sageWoocommerce) {
                    $order = new WC_Order($request['id']);
                    $body = json_decode($request->get_body(), false);
                    $doPiece = $body->{self::TOKEN . "-fdocentete-dopiece"};
                    $doType = (int)$body->{self::TOKEN . "-fdocentete-dotype"};
                    $order = $sageWoocommerce->linkOrderFDocentete($order, $doPiece, $doType, true);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => $sageWoocommerce->getMetaboxSage($order, ignorePingApi: true)
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/deactivate-shipping-zones', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    global $wpdb;
                    $wpdb->get_results($wpdb->prepare("
UPDATE {$wpdb->prefix}woocommerce_shipping_zone_methods
SET is_enabled = 0
WHERE method_id NOT LIKE '" . self::TOKEN . "%'
  AND is_enabled = 1
"));
                    $redirect = wp_get_referer();
                    wp_redirect($redirect);
                    exit();
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/add-website-sage-api', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($settings) {
                    $result = $settings->addWebsiteSageApi(true);
                    if ($result !== true) {
                        return new WP_REST_Response($result, Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    return new WP_REST_Response(null, Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/healthz', [
                'methods' => 'GET',
                'callback' => static function () {
                    return new WP_REST_Response(null, Response::HTTP_OK);
                },
                'permission_callback' => static function () {
                    return true;
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/orders/(?P<id>\d+)/meta-box-order', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($settings) {
                    // this includes import woocommerce_wp_text_input
                    include_once __DIR__ . '/../../woocommerce/includes/admin/wc-meta-box-functions.php';
                    $order = new WC_Order($request['id']);
                    $orderHtml = $settings->getMetaBoxOrder($order);
                    $itemHtml = $settings->getMetaBoxOrderItems($order);
                    return new WP_REST_Response([
                        'orderHtml' => $orderHtml,
                        'itemHtml' => $itemHtml
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/fdocentetes/(?P<doPiece>[A-Za-z0-9]+)/(?P<doType>\d+)/import', args: [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($sageWoocommerce) {
                    $doPiece = $request['doPiece'];
                    $doType = $request['doType'];
                    $headers = [];
                    if (!empty($authorization = $request->get_header('authorization'))) {
                        $headers['authorization'] = $authorization;
                    }
                    $order = new WC_Order();
                    $order = $sageWoocommerce->linkOrderFDocentete($order, $doPiece, $doType, true, headers: $headers);
                    $extendedFDocentetes = $sageWoocommerce->sage->sageGraphQl->getFDocentetes(
                        $doPiece,
                        [$doType],
                        doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                        doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                        getError: true,
                        ignorePingApi: true,
                        getFDoclignes: true,
                        getExpedition: true,
                        addWordpressProductId: true,
                        getUser: true,
                        getLivraison: true,
                        extended: true,
                    );
                    $tasksSynchronizeOrder = $sageWoocommerce->getTasksSynchronizeOrder($order, $extendedFDocentetes);
                    [$message, $order] = $sageWoocommerce->applyTasksSynchronizeOrder($order, $tasksSynchronizeOrder, $headers);
                    return new WP_REST_Response([
                        'id' => $order->get_id(),
                        'message' => $message,
                    ], $message === "" ? 201 : 500);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
            register_rest_route(self::TOKEN . '/v1', '/user/(?P<ctNum>([^&]*))', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) use ($sageGraphQl) {
                    $ctNum = $request['ctNum'];
                    $fComptet = $sageGraphQl->getFComptet($ctNum, ignorePingApi: true);
                    $user = get_users([
                        'meta_key' => self::META_KEY_CT_NUM,
                        'meta_value' => strtoupper($ctNum)
                    ]);
                    if (!empty($user)) {
                        $user = $user[0];
                    } else {
                        $user = null;
                    }
                    return new WP_REST_Response([
                        'fComptet' => $fComptet,
                        'user' => $user,
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can(SageSettings::$capability);
                },
            ]);
        });
        // endregion

        add_filter('woocommerce_shipping_rate_cost', static function (string $cost, WC_Shipping_Rate $wcShippingRate) use ($sageWoocommerce) {
            return (string)($sageWoocommerce->getShippingRateCosts(WC()->cart, $wcShippingRate) ?? $cost);
        }, accepted_args: 2);
        add_filter('woocommerce_shipping_rate_label', static function (string $label, WC_Shipping_Rate $wcShippingRate) {
            if (!str_starts_with($wcShippingRate->get_method_id(), self::TOKEN . '-')) {
                return $label;
            }
            $remove = '[Sage] ';
            if (str_starts_with($label, $remove)) {
                $label = substr($label, strlen($remove));
            }
            return $label;
        }, accepted_args: 2);
    }

    /**
     * Installation. Runs on activation.
     */
    public function install(): void
    {
        update_option(self::TOKEN . '_version', $this->_version);
        // region delete FilesystemAdapter cache
        $this->cache->clear();
        // endregion
        // region delete twig cache
        $dir = str_replace('sage.php', 'templates/cache', $this->file);
        if (is_dir($dir)) {
            $filesystem = new Filesystem();
            $filesystem->remove([$dir]);
        }
        // endregion
        $this->settings->applyDefaultSageEntityMenuOptions();
        $this->settings->addWebsiteSageApi(true);

        // $this->init() is called during activation and add_action init because sometimes add_action init could fail when plugin is installed
        $this->init();
        flush_rewrite_rules();
    }

    public function init(): void
    {
        // Handle localisation.
        $this->load_plugin_textdomain();
        // todo register_post_type here
    }

    /**
     * Load plugin textdomain
     */
    public function load_plugin_textdomain(): void
    {
        $domain = self::TOKEN;
        $locale = apply_filters('plugin_locale', get_locale(), $domain);
        load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, dirname(plugin_basename($this->file)) . '/lang/');
    }

    private function showWrongOptions(): void
    {
        $pDossier = $this->sageGraphQl->getPDossier();
        $sageExpectedOptions = [
            new SageExpectedOption(
                optionName: 'woocommerce_enable_guest_checkout',
                optionValue: 'no',
                trans: __('Allow customers to place orders without an account', 'woocommerce'),
                description: __("Lorsque cette option est activée vos clients ne sont pas obligés de se connecter à leurs comptes pour passer commande et il est donc impossible de créer automatiquement la commande passé dans Woocommerce dans Sage.", 'sage'),
            ),
            new SageExpectedOption(
                optionName: 'woocommerce_calc_taxes',
                optionValue: 'yes',
                trans: __('Enable tax rates and calculations', 'woocommerce'),
                description: __("Cette option doit être activé pour que le plugin Sage fonctionne correctement afin de récupérer les taxes directement renseignées dans Sage.", 'sage'),
            ),
        ];
        if (!is_null($pDossier?->nDeviseCompteNavigation?->dCodeIso)) {
            $sageExpectedOptions[] = new SageExpectedOption(
                optionName: 'woocommerce_currency',
                optionValue: $pDossier->nDeviseCompteNavigation->dCodeIso,
                trans: __('Currency', 'woocommerce'),
                description: __("La devise dans Woocommerce n'est pas la même que dans Sage.", 'sage'),
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
            ?>
            <div class="error">
            <?php
            $fieldsForm = '';
            $optionNames = [];
            foreach ($changes as $sageExpectedOption) {
                $optionValue = $sageExpectedOption->getOptionValue();
                echo "<div>" . __('Le plugin Sage a besoin de modifier l\'option', 'sage') . " <code>" .
                    $sageExpectedOption->getTrans() . "</code> " . __('pour lui donner la valeur', 'sage') . " <code>" .
                    $optionValue . "</code>
<div class='tooltip'>
        <span class='dashicons dashicons-info' style='padding-right: 22px'></span>
        <div class='tooltiptext' style='right: 0'>" . $sageExpectedOption->getDescription() . "</div>
    </div>
</div>";
                $optionName = $sageExpectedOption->getOptionName();
                $fieldsForm .= '<input type="hidden" name="' . $optionName . '" value="' . $optionValue . '">';
                $optionNames[] = $optionName;
            } ?>
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?= $fieldsForm ?>
                <input type="hidden" name="page_options" value="<?= implode(',', $optionNames) ?>"/>
                <input type="hidden" name="_wp_http_referer" value="<?= $_SERVER["REQUEST_URI"] ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="option_page" value="options"/>
                <?php wp_nonce_field('options-options'); ?>
                <p class="submit">
                    <input name="Update" type="submit" class="button-primary"
                           value="<?= __('Mettre à jour', 'sage') ?>">
                </p>
            </form>
            </div><?php
        }
    }

    public function addCustomerMetaFields(?WP_User $user = null): void
    {
        $pCattarifs = $this->sageGraphQl->getPCattarifs();
        $pCatComptas = $this->sageGraphQl->getPCatComptas();
        $userMetaWordpress = null;
        if ($user) {
            $userMetaWordpress = get_user_meta($user->ID);
        }
        echo $this->twig->render('user/formMetaFields.html.twig', [
            'user' => $user,
            'userMetaWordpress' => $userMetaWordpress,
            'pCattarifs' => $pCattarifs,
            'pCatComptas' => $pCatComptas,
        ]);
    }

    /**
     * Create FComptet or update it according to $_POST
     */
    public function saveCustomerUserMetaFields(?int $userId): void
    {
        $nbUpdatedMeta = 0;
        $inSage = (bool)get_option(self::TOKEN . '_auto_create_sage_fcomptet');
        $ctNum = null;
        $newFComptet = false;
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, '_' . self::TOKEN)) {
                $value = trim(preg_replace('/\s\s+/', ' ', $value));
                if ($key === '_' . self::TOKEN . '_creationType') {
                    if ($value === 'new') {
                        $newFComptet = true;
                    } else if ($value === 'none') {
                        $inSage = false;
                    }
                }
                if ($key === self::META_KEY_CT_NUM) {
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
        update_user_meta($userId, '_' . self::TOKEN . '_updateApi', new DateTime());
        [$createdOrUpdatedUserId, $message] = $this->updateUserOrFComptet($ctNum, $userId, newFComptet: $newFComptet);
        if ($newFComptet && is_null($createdOrUpdatedUserId)) {
            $this->deleteSageMetadataForUser($userId);
        }
        if ($message) {
            $redirect = add_query_arg(self::TOKEN . '_message', urlencode($message), wp_get_referer());
            wp_redirect($redirect);
            exit;
        }
    }

    public function canUpdateUserOrFComptet(stdClass $fComptet): array
    {
        // all fields here must be [IsProjected(false)]
        $result = [];
        if ($fComptet->ctType !== TiersTypeEnum::TiersTypeClient->value) {
            $result[] = __("Le compte " . $fComptet->ctNum . " n'est pas un compte client.", 'sage');
        }
        return $result;
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
                    " . __("Vous devez spécifier le numéro de compte Sage", 'sage') . "
                            </div>"];
        }
        if (is_null($fComptet) && !is_null($ctNum)) {
            $ctNum = str_replace(' ', '', strtoupper($ctNum));
            $fComptet = $this->sageGraphQl->getFComptet($ctNum, ignorePingApi: $ignorePingApi);
        }
        if ($newFComptet) {
            if (!is_null($fComptet)) {
                return [null, "<div class='error'>
                    " . __("Vous essayez de créer compte Sage qui existe déjà (" . $ctNum . ")", 'sage') . "
                            </div>"];
            }
            if (is_null($shouldBeUserId)) {
                return [null, "<div class='error'>
                    " . __("Vous devez spécifier l'id compte Wordpress", 'sage') . "
                            </div>"];
            }
            $fComptet = $this->sageGraphQl->createFComptet(
                userId: $shouldBeUserId,
                ctNum: $ctNum,
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
                    " . __("Le compte Sage n'a pas pu être " . $word, 'sage') . "
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
                        " . __("Ce numéro de compte Sage est déjà assigné à un utilisateur Wordpress", 'sage') . "
                                </div>"];
            }
        }
        [$userId, $userFromSage, $metadata] = $this->sageWoocommerce->convertSageUserToWoocommerce(
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
        $updateWordpress = empty(get_user_meta($userId, '_' . self::TOKEN . '_updateApi', true));
        if ($updateWordpress) {
            $wpUser = new WP_User($userId);
            if ($wpUser->user_login !== $userFromSage->user_login) {
                wp_update_user($userFromSage);
            }
            foreach ($metadata as $key => $value) {
                update_user_meta($userId, $key, $value);
            }
        } else {
            if (!$newFComptet) { // no need update fComptet as sageGraphQl->createFComptet already update fComptet
                $fComptet = $this->sageGraphQl->updateFComptetFromWebsite(
                    ctNum: $ctNum,
                    getError: true,
                );
                if (is_string($fComptet)) {
                    return [null, $fComptet];
                }
            }
            // no need because it's done directly by Sage Api
            // update_user_meta($userId, '_' . self::TOKEN . '_updateApi', null);
        }

        $url = "<strong><span style='display: block; clear: both;'><a href='" . get_admin_url() . "user-edit.php?user_id=" . $userId . "'>" . __("Voir l'utilisateur", 'sage') . "</a></span></strong>";
        if (!$newUser) {
            return [$userId, "<div class='notice notice-success is-dismissible'>
                        " . __('L\'utilisateur a été modifié', 'sage') . $url . "
                                </div>"];
        }
        return [$userId, "<div class='notice notice-success is-dismissible'>
                        " . __('L\'utilisateur a été créé', 'sage') . $url . "
                                </div>"];
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
", [self::META_KEY_CT_NUM, $ctNum]));
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
WHERE user_id = %s AND meta_key LIKE '_" . self::TOKEN . "_%'
        ", [$userId]));
    }

    /**
     * Main sage Instance
     *
     * Ensures only one instance of sage is loaded or can be loaded.
     *
     * @param string $file File instance.
     * @param string $version Version parameter.
     *
     * @return Sage sage instance
     * @see sage()
     */
    public static function instance(string $file = '', string $version = '1.0.0'): self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }

        return self::$_instance;
    }

    private function afterCreateOrEditOrder(WC_Order $order, bool $isNewOrder = false): void
    {
        if (
            array_key_exists(self::TOKEN . '-fdocentete-dotype', $_POST) &&
            array_key_exists(self::TOKEN . '-fdocentete-dopiece', $_POST) &&
            is_numeric($_POST[self::TOKEN . '-fdocentete-dotype']) &&
            !empty($_POST[self::TOKEN . '-fdocentete-dopiece'])
        ) {
            $this->sageWoocommerce->linkOrderFDocentete(
                $order,
                $_POST[self::TOKEN . '-fdocentete-dopiece'],
                (int)$_POST[self::TOKEN . '-fdocentete-dotype'],
                true,
            );
        } else if ($isNewOrder) {
            $optionName = self::TOKEN . '_auto_create_sage_fdocentete';
            if (get_option($optionName)) {
                $order->update_meta_data($optionName, true);
                $order->save();
            }
        }
    }

    public static function getArRef(int $postId): mixed
    {
        return get_post_meta($postId, self::META_KEY_AR_REF, true);
    }

    public static function getValidWordpressMail(?string $value): string|null
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

    public static function getName(?string $intitule, ?string $contact): string
    {
        $intitule = trim($intitule ?? '');
        $contact = trim($contact ?? '');
        $name = $intitule;
        if (empty($name)) {
            $name = $contact;
        }
        return $name;
    }

    public static function getFirstNameLastName(...$fullNames): array
    {
        foreach ($fullNames as $fullName) {
            if (empty($fullName)) {
                continue;
            }
            $fullName = trim($fullName);
            $lastName = (!str_contains($fullName, ' ')) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $fullName);
            $firstName = trim(preg_replace('#' . preg_quote($lastName, '#') . '#', '', $fullName));
            return [$firstName, $lastName];
        }
        return ['', ''];
    }

    public static function createAddressWithFComptet(StdClass $fComptet): StdClass
    {
        $r = new StdClass();
        $r->liIntitule = $fComptet->ctIntitule;
        $r->liAdresse = $fComptet->ctAdresse;
        $r->liComplement = $fComptet->ctComplement;
        $r->liCodePostal = $fComptet->ctCodePostal;
        $r->liPrincipal = 0;
        $r->liVille = $fComptet->ctVille;
        $r->liPays = $fComptet->ctPays;
        $r->liPaysCode = $fComptet->ctPaysCode;
        $r->liContact = $fComptet->ctContact;
        $r->liTelephone = $fComptet->ctTelephone;
        $r->liEmail = $fComptet->ctEmail;
        $r->liCodeRegion = $fComptet->ctCodeRegion;
        $r->liAdresseFact = 0;
        return $r;
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

    public function getUserWordpressIdForSage(int $userId)
    {
        return get_user_meta($userId, self::META_KEY_CT_NUM, true);
    }

    public function getAvailableUserName(string $ctNum): string
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare("
SELECT user_login
FROM {$wpdb->users}
WHERE user_login LIKE %s
", [$ctNum . '%']));
        if (!empty($r)) {
            $names = array_map(static function (stdClass $user) {
                return $user->user_login;
            }, $r);
            $result = null;
            $i = 1;
            while (is_null($result)) {
                $newName = $ctNum . $i;
                if (!in_array($newName, $names, true)) {
                    $result = $newName;
                }
                $i++;
            }
            return $result;
        }
        return $ctNum;
    }

    public function createResource(
        string  $url,
        string  $method,
        array   $body,
        ?string $deleteKey,
        ?string $deleteValue,
        array   $headers = [],
    ): array
    {
        if (!is_null($deleteKey) && !is_null($deleteValue)) {
            $this->deleteMetaTrashResource($deleteKey, $deleteValue);
        }
        $response = SageRequest::selfRequest($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                ...$headers,
            ],
            'method' => $method,
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $responseError = null;
        if ($response instanceof WP_Error) {
            $responseError = "<div class='notice notice-error is-dismissible'>
                                <pre>" . $response->get_error_code() . "</pre>
                                <pre>" . $response->get_error_message() . "</pre>
                                </div>";
        }

        if ($response instanceof WP_Error) {
            $responseError = "<div class='notice notice-error is-dismissible'>
                                <pre>" . json_encode($response, JSON_THROW_ON_ERROR) . "</pre>
                                </div>";
        } else if (!in_array($response["response"]["code"], [Response::HTTP_OK, Response::HTTP_CREATED], true)) {
            $responseError = "<div class='notice notice-error is-dismissible'>
                                <pre>" . $response['response']['code'] . "</pre>
                                <pre>" . $response['body'] . "</pre>
                                </div>";
        }
        return [$response, $responseError];
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
  AND {$wpdb->postmeta}.meta_key LIKE '_" . self::TOKEN . "_%'
        ", [$key, $value]));
    }

    /**
     * Register post type function.
     * https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
     * You must call register_post_type() before the admin_init hook and after the after_setup_theme hook. A good hook to use is the init action hook.
     *
     * @param string $post_type Post Type.
     * @param string $plural Plural Label.
     * @param string $single Single Label.
     * @param string $description Description.
     * @param array $options Options array.
     */
    public function register_post_type(
        string $post_type = '',
        string $plural = '',
        string $single = '',
        string $description = '',
        array  $options = [],
    ): bool|SagePostType
    {

        if ($post_type === '' || $plural === '' || $single === '') {
            return false;
        }

        return new SagePostType($post_type, $plural, $single, $description, $options);
    }

    /**
     * Wrapper function to register a new taxonomy.
     *
     * @param string $taxonomy Taxonomy.
     * @param string $plural Plural Label.
     * @param string $single Single Label.
     * @param array $post_types Post types to register this taxonomy for.
     * @param array $taxonomy_args Taxonomy arguments.
     */
    public function register_taxonomy(
        string $taxonomy = '',
        string $plural = '',
        string $single = '',
        array  $post_types = [],
        array  $taxonomy_args = [],
    ): bool|SageTaxonomy
    {

        if ($taxonomy === '' || $plural === '' || $single === '') {
            return false;
        }

        return new SageTaxonomy($taxonomy, $plural, $single, $post_types, $taxonomy_args);
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of sage is forbidden')), esc_attr($this->_version));

    }

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of sage is forbidden')), esc_attr($this->_version));
    }
}
