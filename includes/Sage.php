<?php

namespace App;

use App\lib\SageAdminApi;
use App\lib\SagePostType;
use App\lib\SageTaxonomy;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

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

    /**
     * Constructor funtion.
     *
     * @param string|null $file File constructor.
     * @param string|null $_version Plugin version.
     */
    public function __construct(public ?string $file = '', public ?string $_version = '1.0.0')
    {
        $this->dir = dirname($this->file);
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
            $this->twig->addFilter(new TwigFilter('trans', static function (string $string) {
                return __($string, self::$_token);
            }));
            $this->twig->addFilter(new TwigFilter('esc_attr', static function (string $string) {
                return esc_attr($string);
            }));
            $this->twig->addFilter(new TwigFilter('selected', static function (bool $selected) {
                return selected($selected, true, false);
            }));
            $this->twig->addFilter(new TwigFilter('disabled', static function (bool $disabled) {
                return disabled($disabled, true, false);
            }));
        }

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

        // region enqueue js && css
        // Load frontend JS & CSS.
        add_action('wp_enqueue_scripts', function (): void {
            $this->enqueue_styles();
        }, 10);
        add_action('wp_enqueue_scripts', function (): void {
            $this->enqueue_scripts();
        }, 10);

        // Load admin JS & CSS.
        add_action('admin_enqueue_scripts', function (string $hook = ''): void {
            $this->admin_enqueue_scripts($hook);
        }, 10, 1);
        add_action('admin_enqueue_scripts', function (string $hook = ''): void {
            $this->admin_enqueue_styles($hook);
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
                if (!array_key_exists($pluginId, $allPlugins)) {
                    add_action('admin_notices', static function (): void {
                        ?>
                        <div class="error"><p>
                            <?= __('Sage plugin require WooCommerce to be installed.', 'sage') ?>
                        </p>
                        </div><?php
                    });
                } elseif (!is_plugin_active($pluginId)) {
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
        $this->_log_version_number();
    }

    /**
     * Log the plugin version number.
     */
    private function _log_version_number(): void
    {
        update_option(self::$_token . '_version', $this->_version);
    }

    public function init(): void
    {
        $this->load_localisation();
        // todo register_post_type here
    }

    /**
     * Load plugin localisation
     */
    public function load_localisation(): void
    {
        load_plugin_textdomain('sage', false, dirname(plugin_basename($this->file)) . '/lang/');
    }

    /**
     * Load frontend CSS.
     */
    public function enqueue_styles(): void
    {
        wp_register_style(self::$_token . '-frontend', esc_url($this->assets_url) . 'css/frontend.css', [], $this->_version);
        wp_enqueue_style(self::$_token . '-frontend');
    }

    /**
     * Load frontend Javascript.
     */
    public function enqueue_scripts(): void
    {
        wp_register_script(self::$_token . '-frontend', esc_url($this->assets_url) . 'js/frontend' . $this->script_suffix . '.js', ['jquery'], $this->_version, true);
        wp_enqueue_script(self::$_token . '-frontend');
    }

    /**
     * Load admin Javascript.
     *
     *
     * @param string $hook Hook parameter.
     */
    public function admin_enqueue_scripts(string $hook = ''): void
    {
        wp_register_script(self::$_token . '-admin', esc_url($this->assets_url) . 'js/admin' . $this->script_suffix . '.js', ['jquery'], $this->_version, true);
        wp_enqueue_script(self::$_token . '-admin');
    }

    /**
     * Admin enqueue style.
     *
     * @param string $hook Hook parameter.
     */
    public function admin_enqueue_styles(string $hook = ''): void
    {
        wp_register_style(self::$_token . '-admin', esc_url($this->assets_url) . 'css/admin.css', [], $this->_version);
        wp_enqueue_style(self::$_token . '-admin');
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
     * @return Sage|null sage instance
     * @see sage()
     */
    public static function instance(string $file = '', string $version = '1.0.0'): ?self
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
