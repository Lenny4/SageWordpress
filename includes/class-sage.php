<?php
/**
 * Main plugin class file.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
class sage
{

    /**
     * The single instance of sage.
     *
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null; //phpcs:ignore

    /**
     * Local instance of sage_Admin_API
     *
     * @var sage_Admin_API|null
     */
    public $admin = null;

    /**
     * Settings class object
     *
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $settings = null;

    /**
     * The version number.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version; //phpcs:ignore

    /**
     * The token.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token = 'sage'; //phpcs:ignore

    /**
     * The main plugin file.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;

    /**
     * Suffix for JavaScripts.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    /**
     * Constructor funtion.
     *
     * @param string $file File constructor.
     * @param string $version Plugin version.
     */
    public function __construct($file = '', $version = '1.0.0')
    {
        $this->_version = $version;

        // Load plugin environment variables.
        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        register_activation_hook($this->file, function (): void {
            $this->install();
        });

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

        // Load API for generic admin functions.
        if (is_admin()) {
            $this->admin = new sage_Admin_API();
        }

        // Handle localisation.
        $this->load_plugin_textdomain();
        add_action('init', function (): void {
            $this->load_localisation();
        }, 0);
    } // End __construct ()

/**
     * Installation. Runs on activation.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function install()
    {
        $this->_log_version_number();
    }

/**
     * Log the plugin version number.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    private function _log_version_number()
    { //phpcs:ignore
        update_option($this->_token . '_version', $this->_version);
    }

    /**
     * Load frontend CSS.
     *
     * @access  public
     * @return void
     * @since   1.0.0
     */
    public function enqueue_styles()
    {
        wp_register_style($this->_token . '-frontend', esc_url($this->assets_url) . 'css/frontend.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-frontend');
    } // End enqueue_styles ()

    /**
     * Load frontend Javascript.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function enqueue_scripts()
    {
        wp_register_script($this->_token . '-frontend', esc_url($this->assets_url) . 'js/frontend' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
        wp_enqueue_script($this->_token . '-frontend');
    } // End enqueue_scripts ()

    /**
     * Load admin Javascript.
     *
     * @access  public
     *
     * @param string $hook Hook parameter.
     *
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_scripts($hook = '')
    {
        wp_register_script($this->_token . '-admin', esc_url($this->assets_url) . 'js/admin' . $this->script_suffix . '.js', array('jquery'), $this->_version, true);
        wp_enqueue_script($this->_token . '-admin');
    } // End admin_enqueue_styles ()

    /**
     * Admin enqueue style.
     *
     * @param string $hook Hook parameter.
     *
     * @return void
     */
    public function admin_enqueue_styles($hook = '')
    {
        wp_register_style($this->_token . '-admin', esc_url($this->assets_url) . 'css/admin.css', array(), $this->_version);
        wp_enqueue_style($this->_token . '-admin');
    } // End admin_enqueue_scripts ()

    /**
     * Load plugin textdomain
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function load_plugin_textdomain()
    {
        $domain = 'sage';

        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, dirname(plugin_basename($this->file)) . '/lang/');
    } // End load_localisation ()

    /**
     * Load plugin localisation
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function load_localisation()
    {
        load_plugin_textdomain('sage', false, dirname(plugin_basename($this->file)) . '/lang/');
    } // End load_plugin_textdomain ()

    /**
     * Main sage Instance
     *
     * Ensures only one instance of sage is loaded or can be loaded.
     *
     * @param string $file File instance.
     * @param string $version Version parameter.
     *
     * @return Object sage instance
     * @see sage()
     * @since 1.0.0
     * @static
     */
    public static function instance($file = '', $version = '1.0.0')
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }

        return self::$_instance;
    } // End instance ()

        /**
     * Register post type function.
     *
     * @param string $post_type Post Type.
     * @param string $plural Plural Label.
     * @param string $single Single Label.
     * @param string $description Description.
     * @param array $options Options array.
     *
     * @return bool|string|sage_Post_Type
     */
    public function register_post_type($post_type = '', $plural = '', $single = '', $description = '', $options = array())
    {

        if (!$post_type || !$plural || !$single) {
            return false;
        }

        return new sage_Post_Type($post_type, $plural, $single, $description, $options);
    } // End __clone ()

        /**
     * Wrapper function to register a new taxonomy.
     *
     * @param string $taxonomy Taxonomy.
     * @param string $plural Plural Label.
     * @param string $single Single Label.
     * @param array $post_types Post types to register this taxonomy for.
     * @param array $taxonomy_args Taxonomy arguments.
     *
     * @return bool|string|sage_Taxonomy
     */
    public function register_taxonomy($taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array())
    {

        if (!$taxonomy || !$plural || !$single) {
            return false;
        }

        return new sage_Taxonomy($taxonomy, $plural, $single, $post_types, $taxonomy_args);
    } // End __wakeup ()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of sage is forbidden')), esc_attr($this->_version));

    } // End install ()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of sage is forbidden')), esc_attr($this->_version));
    } // End _log_version_number ()

}
