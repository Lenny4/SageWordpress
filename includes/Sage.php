<?php

namespace App;

use App\lib\SageAdminApi;
use App\lib\SageGraphQl;
use App\lib\SagePostType;
use App\lib\SageTaxonomy;
use App\lib\SageWoocommerce;
use App\Utils\SageTranslationUtils;
use Lead\Dir\Dir;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WP_Upgrader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
final class Sage
{

    /**
     * The token.
     */
    public static string $_token = 'sage';

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
        $this->cache = new FilesystemAdapter();

        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

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

        $this->twig->addFilter(new TwigFilter('trans', static fn(string $string) => __($string, self::$_token)));
        $this->twig->addFilter(new TwigFilter('esc_attr', static fn(string $string) => esc_attr($string)));
        $this->twig->addFilter(new TwigFilter('selected', static fn(bool $selected) => selected($selected, true, false)));
        $this->twig->addFilter(new TwigFilter('disabled', static fn(bool $disabled) => disabled($disabled, true, false)));
        $this->twig->addFilter(new TwigFilter('bytesToString', static fn(array $bytes): string => implode('', array_map("chr", $bytes))));
        $this->twig->addFilter(new TwigFilter('wp_nonce_field', static fn(string $action) => wp_nonce_field($action)));
        $this->twig->addFunction(new TwigFunction('getTranslations', static fn(): array => SageTranslationUtils::getTranslations()));
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
                    $fieldsOrder[$f['name']] = $i;
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
                $r[] = $obj[$mandatoryField];
            }

            return implode('|', $r);
        }));
        $this->twig->addFunction(new TwigFunction('getApiHostUrl', static fn(): string => get_option(Sage::$_token . '_api_host_url')));

        // endregion

        register_activation_hook($this->file, function (): void {
            $this->install();
            // $this->init() is called during activation and add_action init because sometimes add_action init could fail when plugin is installed
            $this->init();
            flush_rewrite_rules();
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
                // region delete FilesystemAdapter cache
                $cache = new FilesystemAdapter();
                $cache->clear();
                // endregion
                // region delete twig cache
                $dir = str_replace('sage.php', 'templates/cache', $this->file);
                if (is_dir($dir)) {
                    Dir::remove($dir, ['recursive' => true]);
                }
                // endregion
            }
        }, 10, 2);

        // region enqueue js && css
        // Load frontend JS & CSS.
        add_action('wp_enqueue_scripts', function (): void {
            wp_register_style(self::$_token . '-frontend', esc_url($this->assets_url) . 'css/frontend.css', [], $this->_version);
            wp_enqueue_style(self::$_token . '-frontend');
            wp_register_script(self::$_token . '-frontend', esc_url($this->assets_url) . 'js/frontend' . $this->script_suffix . '.js', ['jquery'], $this->_version, true);
            wp_enqueue_script(self::$_token . '-frontend');
        }, 10);

        // Load admin JS & CSS.
        add_action('admin_enqueue_scripts', function (string $hook = ''): void {
            wp_register_script(self::$_token . '-admin', esc_url($this->assets_url) . 'js/admin' . $this->script_suffix . '.js', ['jquery'], $this->_version, true);
            wp_enqueue_script(self::$_token . '-admin');
            wp_register_style(self::$_token . '-admin', esc_url($this->assets_url) . 'css/admin.css', [], $this->_version);
            wp_enqueue_style(self::$_token . '-admin');
        }, 10, 1);
        // endregion

        // Load API for generic admin functions.
        if (is_admin()) {
            $this->admin = new SageAdminApi($this);
        }

        // Handle localisation.
        $this->load_plugin_textdomain();
        add_action('init', function (): void {
            // $this->init() is called during activation and add_action init because sometimes add_action init could fail when plugin is installed
            $this->init();
        }, 0);

        add_action('admin_init', static function (): void {
            if (is_admin() && current_user_can('activate_plugins')) {
                $allPlugins = get_plugins();
                $pluginId = 'woocommerce/woocommerce.php';
                $isWooCommerceInstalled = array_key_exists($pluginId, $allPlugins);
                add_action('admin_notices', static function () use ($isWooCommerceInstalled): void {
                    ?>
                    <div id="<?= Sage::$_token ?>_tasks" class="notice notice-info">
                        <p><span>Sage tasks.</span></p>
                    </div>
                    <?php
                    if (!$isWooCommerceInstalled) {
                        ?>
                        <div class="error"><p>
                                <?= __('Sage plugin require WooCommerce to be installed.', 'sage') ?>
                            </p></div>
                        <?php
                    }
                    ?>
                    <?php
                });
                if ($isWooCommerceInstalled && !is_plugin_active($pluginId)) {
                    add_action('admin_notices', static function (): void {
                        ?>
                        <div class="error"><p>
                            <?= __('Sage plugin require WooCommerce to be activated.', 'sage') ?>
                        </p>
                        </div><?php
                    });
                }
            }
        });
    }

    /**
     * Installation. Runs on activation.
     */
    public function install(): void
    {
        update_option(self::$_token . '_version', $this->_version);
    }

    public function init(): void
    {
        load_plugin_textdomain('sage', false, dirname(plugin_basename($this->file)) . '/lang/'); // load_localisation
        // todo register_post_type here
    }

    /**
     * Load plugin textdomain
     */
    public function load_plugin_textdomain(): void
    {
        $domain = 'sage';

        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, dirname(plugin_basename($this->file)) . '/lang/');
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
