<?php

namespace App;

use App\controllers\AdminController;
use App\controllers\ApiController;
use App\services\RequestService;
use App\services\TwigService;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
final class Sage
{
    public final const TOKEN = 'egas';
    private static ?Sage $instance = null;
    private Container $container;
    public static array $paginationRange = [20, 50, 100];

    public static function getInstance(string $file = '', string $version = '1.0.0'): self
    {
        if (self::$instance === null) {
            self::$instance = new self($file, $version);
        }
        return self::$instance;
    }

    private function __construct(public ?string $file = '', public ?string $_version = '1.0.0')
    {
        if (!$this->isWooCommerceActive()) {
            add_action('admin_notices', function () {
                // todo use twig
                echo '<div class="notice notice-error"><p>' .
                    __('Egas a besoin de Woocommerce pour fonctionner.', Sage::TOKEN) .
                    '</p></div>';
            });
            return;
        }
        $this->container = new Container();
        $this->registerServices();
        $this->registerHooks();
    }

    private function isWooCommerceActive(): bool
    {
        return is_plugin_active('woocommerce/woocommerce.php');
    }

    private function registerServices(): void
    {
        $this->container->register(RequestService::class, function () {
            return new RequestService();
        });
        $this->container->register(TwigService::class, function () {
            return new TwigService($this->file);
        });

        // todo delete
//        $this->container->register('user_service', function ($container) {
//            return new Services\UserService(
//                $container->get('logger'),
//                $container->get('validator')
//            );
//        });

        // Register controllers
        $this->container->register(AdminController::class, function ($container) {
            return new AdminController();
        });
    }

    private function registerHooks(): void
    {
        register_activation_hook(__FILE__, function () {
            flush_rewrite_rules();
        });
        register_deactivation_hook(__FILE__, function () {
            flush_rewrite_rules();
        });

        add_action('init', function () {

        });
        add_action('admin_menu', function () {
            /** @var AdminController $adminController */
            $adminController = $this->container->get(AdminController::class);
            $adminController->registerMenu();
        });

        $assetsDistUrl = esc_url(trailingslashit(plugins_url('/dist/', $this->file)));
        $scriptSuffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        add_action('wp_enqueue_scripts', function () use($assetsDistUrl, $scriptSuffix): void {
            wp_register_style(self::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend.css', [], $this->_version);
            wp_enqueue_style(self::TOKEN . '-frontend');
            wp_register_script(self::TOKEN . '-frontend', esc_url($assetsDistUrl) . 'frontend' . $scriptSuffix . '.js', ['jquery'], $this->_version, true);
            wp_enqueue_script(self::TOKEN . '-frontend');
        }, 10);
        add_action('admin_enqueue_scripts', function () use($assetsDistUrl, $scriptSuffix): void {
            wp_register_script(self::TOKEN . '-admin', esc_url($assetsDistUrl) . 'admin' . $scriptSuffix . '.js', ['jquery'], $this->_version, true);
            wp_enqueue_script(self::TOKEN . '-admin');
            wp_register_style(self::TOKEN . '-admin', esc_url($assetsDistUrl) . 'admin.css', [], $this->_version);
            wp_enqueue_style(self::TOKEN . '-admin');
        }, 10, 1);

        $this->registerWooCommerceHooks();

        add_action('rest_api_init', function () {
            $apiController = new ApiController();
            $apiController->registerRoutes();
        });
    }

    private function registerWooCommerceHooks(): void
    {
        add_action('woocommerce_new_order', static function (int $orderId, WC_Order $order): void {
            // todo
        }, accepted_args: 2);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}

class Container
{
    private $services = [];
    private $instances = [];

    public function register(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }

    public function get(string $name)
    {
        if (!isset($this->instances[$name])) {
            if (!isset($this->services[$name])) {
                throw new \Exception("Service '$name' not found");
            }
            $this->instances[$name] = $this->services[$name]($this);
        }

        return $this->instances[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }
}
