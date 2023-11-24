<?php

namespace App;

use App\enum\WebsiteEnum;
use App\lib\SageGraphQl;
use App\lib\SageRequest;
use App\Utils\SageTranslationUtils;
use stdClass;
use WP_Application_Passwords;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class.
 */
final class SageSettings
{

    /**
     * Prefix for plugin settings.
     */
    public static string $base = 'sage_';

    public static string $capability = 'manage_options';

    public static array $defaultFComptetFields = [
        'ctNum',
        'ctIntitule',
        'ctContact',
        'ctEmail',
    ];

    public static array $defaultFDocenteteFields = [
        'doPiece',
        'doType',
        'doDate',
    ];

    public static array $paginationRange = [20, 50, 100];

    public static int $defaultPagination = 20;

    /**
     * The single instance of SageSettings.
     */
    private static ?self $_instance = null;

    /**
     * Available settings for plugin.
     */
    public array $settings = [];

    /**
     * Constructor function.
     *
     * @param Sage|null $sage Parent object.
     */
    public function __construct(public ?Sage $sage)
    {
        // Initialise settings.
        add_action('init', function (): void {
            $this->init();
        }, 11);

        // Register plugin settings.
        add_action('admin_init', function (): void {
            $this->register_settings();
        });

        // Add settings page to menu.
        add_action('admin_menu', function (): void {
            $this->add_menu_item();
        });

        // Add settings link to plugins page.
        add_filter(
            'plugin_action_links_' . plugin_basename($this->sage->file),
            fn(array $links): array => $this->add_settings_link($links)
        );

        // Configure placement of plugin settings page. See readme for implementation.
        add_filter(self::$base . 'menu_settings', fn(array $settings = []): array => $this->configure_settings($settings));
    }

    /**
     * Initialise settings
     */
    public function init(): void
    {
        $this->settings = $this->settings_fields();
        $this->add_website_sage_api();
    }

    /**
     * Build settings fields
     *
     * @return array Fields to be displayed on settings page
     */
    private function settings_fields(): array
    {
        $settings = [
            'api' => [
                'title' => __('Api', 'sage'),
                'description' => __('These are fairly standard form input fields.', 'sage'),
                'fields' => [
                    [
                        'id' => 'api_host_url',
                        'label' => __('Api host url', 'sage'),
                        'description' => __('Can be found here.', 'sage'),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => __('192.168.0.1', 'sage')
                    ],
                    [
                        'id' => 'api_key',
                        'label' => __('Api key', 'sage'),
                        'description' => __('Can be found here.', 'sage'),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => __('Placeholder text', 'sage')
                    ],
                    [
                        'id' => 'sync_products',
                        'label' => __('Synchronize products', 'sage'),
                        'description' => __("All products created with Sage will be automatically create in Wordpress and vice versa", 'sage'),
                        'type' => 'checkbox',
                        'default' => ''
                    ],
                    [
                        'id' => 'sync_sales_document',
                        'label' => __('Synchronize Sales documents', 'sage'),
                        'description' => __("All sales documents created with Sage will be automatically create in Wordpress and vice versa", 'sage'),
                        'type' => 'checkbox',
                        'default' => ''
                    ],
                    [
                        'id' => 'text_field',
                        'label' => __('Some Text', 'sage'),
                        'description' => __('This is a standard text field.', 'sage'),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => __('Placeholder text', 'sage')
                    ],
                    [
                        'id' => 'password_field',
                        'label' => __('A Password', 'sage'),
                        'description' => __('This is a standard password field.', 'sage'),
                        'type' => 'password',
                        'default' => '',
                        'placeholder' => __('Placeholder text', 'sage')
                    ],
                    [
                        'id' => 'secret_text_field',
                        'label' => __('Some Secret Text', 'sage'),
                        'description' => __('This is a secret text field - any data saved here will not be displayed after the page has reloaded, but it will be saved.', 'sage'),
                        'type' => 'text_secret',
                        'default' => '',
                        'placeholder' => __('Placeholder text', 'sage')
                    ],
                    [
                        'id' => 'text_block',
                        'label' => __('A Text Block', 'sage'),
                        'description' => __('This is a standard text area.', 'sage'),
                        'type' => 'textarea',
                        'default' => '',
                        'placeholder' => __('Placeholder text for this textarea', 'sage')
                    ],
                    [
                        'id' => 'single_checkbox',
                        'label' => __('An Option', 'sage'),
                        'description' => __("A standard checkbox - if you save this option as checked then it will store the option as 'on', otherwise it will be an empty string.", 'sage'),
                        'type' => 'checkbox',
                        'default' => ''
                    ],
                    [
                        'id' => 'select_box',
                        'label' => __('A Select Box', 'sage'),
                        'description' => __('A standard select box.', 'sage'),
                        'type' => 'select',
                        'options' => ['drupal' => 'Drupal', 'joomla' => 'Joomla', 'wordpress' => 'WordPress'],
                        'default' => 'wordpress'
                    ],
                    [
                        'id' => 'radio_buttons',
                        'label' => __('Some Options', 'sage'),
                        'description' => __('A standard set of radio buttons.', 'sage'),
                        'type' => 'radio',
                        'options' => ['superman' => 'Superman', 'batman' => 'Batman', 'ironman' => 'Iron Man'],
                        'default' => 'batman'
                    ],
                    [
                        'id' => 'multiple_checkboxes',
                        'label' => __('Some Items', 'sage'),
                        'description' => __('You can select multiple items and they will be stored as an array.', 'sage'),
                        'type' => 'checkbox_multi',
                        'options' =>
                            ['square' => 'Square', 'circle' => 'Circle', 'rectangle' => 'Rectangle', 'triangle' => 'Triangle'],
                        'default' => ['circle', 'triangle']
                    ],
                ]
            ],
            'fComptet' => [
                'title' => __('Clients', 'sage'),
                'description' => __("These are some extra input fields that maybe aren't as common as the others.", 'sage'),
                'fields' => [
                    [
                        'id' => 'fComptet_fields',
                        'label' => __('Fields to show', 'sage'),
                        'description' => __('Please select the fields to show on the table.', 'sage'),
                        'type' => '2_select_multi',
                        'options' => $this->getFieldsForEntity('FComptet', SageTranslationUtils::TRANS_FCOMPTETS),
                        'default' => self::$defaultFComptetFields,
                    ],
                    [
                        'id' => 'fComptet_perPage',
                        'label' => __('Default per page', 'sage'),
                        'description' => __('Please select the number of clients to show on the table.', 'sage'),
                        'type' => 'select',
                        'options' => self::put_values_in_keys(self::$paginationRange),
                        'default' => (string)self::$defaultPagination
                    ],
                    [
                        'id' => 'number_field',
                        'label' => __('A Number', 'sage'),
                        'description' => __('This is a standard number field - if this field contains anything other than numbers then the form will not be submitted.', 'sage'),
                        'type' => 'number',
                        'default' => '',
                        'placeholder' => __('42', 'sage')
                    ],
                    [
                        'id' => 'colour_picker',
                        'label' => __('Pick a colour', 'sage'),
                        'description' => __("This uses WordPress' built-in colour picker - the option is stored as the colour's hex code.", 'sage'),
                        'type' => 'color',
                        'default' => '#21759B'
                    ],
                    [
                        'id' => 'an_image',
                        'label' => __('An Image', 'sage'),
                        'description' => __('This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an imge the thumbnail will display above these buttons.', 'sage'),
                        'type' => 'image',
                        'default' => '',
                        'placeholder' => ''
                    ],
                    [
                        'id' => 'multi_select_box',
                        'label' => __('A Multi-Select Box', 'sage'),
                        'description' => __('A standard multi-select box - the saved data is stored as an array.', 'sage'),
                        'type' => 'select_multi',
                        'options' => ['linux' => 'Linux', 'mac' => 'Mac', 'windows' => 'Windows'],
                        'default' => ['linux']
                    ],
                ]
            ],
            'fDocentete' => [
                'title' => __('Documents', 'sage'),
                'description' => __("Gestion Commerciale / Menu Traitement / Documents des ventes, des achats, des stocks et internes / Fenêtre Document", 'sage'),
                'fields' => [
                    [
                        'id' => 'fDocentete_fields',
                        'label' => __('Fields to show', 'sage'),
                        'description' => __('Please select the fields to show on the table.', 'sage'),
                        'type' => '2_select_multi',
                        'options' => $this->getFieldsForEntity('FDocentete', SageTranslationUtils::TRANS_FDOCENTETES),
                        'default' => self::$defaultFDocenteteFields,
                    ],
                    [
                        'id' => 'fDocentete_perPage',
                        'label' => __('Default per page', 'sage'),
                        'description' => __('Please select the number of clients to show on the table.', 'sage'),
                        'type' => 'select',
                        'options' => self::put_values_in_keys(self::$paginationRange),
                        'default' => (string)self::$defaultPagination
                    ],
                ]
            ],
        ];
        return apply_filters(Sage::$_token . '_settings_fields', $settings);
    }

    private function getFieldsForEntity(string $object, string $transDomain): array
    {
        $fieldsObject = array_filter(SageGraphQl::getTypeModel($object)?->data?->__type?->fields ?? [], static fn(stdClass $fComptet): bool => $fComptet->type->kind !== 'OBJECT' &&
            $fComptet->type->kind !== 'LIST' &&
            $fComptet->type->ofType?->kind !== 'LIST');
        $trans = SageTranslationUtils::getTranslations();
        $objectFields = [];
        foreach ($fieldsObject as $fieldObject) {
            $objectFields[$fieldObject->name] = $trans[$transDomain][$fieldObject->name];
        }

        return $objectFields;
    }

    public static function put_values_in_keys(array $array): array
    {
        $r = [];
        foreach ($array as $v) {
            $r[$v] = $v;
        }

        return $r;
    }

    private function add_website_sage_api(): void
    {
        if (
            !(array_key_exists('settings-updated', $_GET) &&
                $_GET["settings-updated"] === 'true' &&
                $_GET["page"] === self::$base . 'settings') ||
            !current_user_can(self::$capability)
        ) {
            return;
        }

        $applicationPasswordOption = self::$base . 'application-passwords';
        $userApplicationPassword = get_option($applicationPasswordOption, null);
        $user_id = get_current_user_id();
        $optionHasPassword = false;
        if (!is_null($userApplicationPassword)) {
            $passwords = WP_Application_Passwords::get_user_application_passwords($userApplicationPassword);
            $optionHasPassword = current(array_filter($passwords, static fn(array $password): bool => $password['name'] === $applicationPasswordOption)) !== false;
        }

        if (
            !$optionHasPassword ||
            !$this->is_api_authenticated()
        ) {
            $newPassword = $this->create_application_password($user_id, $applicationPasswordOption);
        }
    }

    private function is_api_authenticated(): bool
    {
        $response = SageRequest::apiRequest('/Website/' . $_SERVER['HTTP_HOST'] . '/Authorization');
        return $response === 'true';
    }

    /**
     * https://developer.wordpress.org/rest-api/reference/application-passwords/#create-a-application-password
     * todo create TU to check if this work with every wordpress version
     */
    private function create_application_password(string $user_id, string $applicationPasswordOption): string
    {
        $passwords = WP_Application_Passwords::get_user_application_passwords($user_id);
        $currentPassword = current(array_filter($passwords, static fn(array $password): bool => $password['name'] === $applicationPasswordOption));
        if ($currentPassword !== false) {
            WP_Application_Passwords::delete_application_password($user_id, $currentPassword["uuid"]);
        }

        $response = SageRequest::selfRequest('/wp-json/wp/v2/users/' . $user_id . '/application-passwords', [
            'headers' => [
                'Content-Length' => (string)strlen('name=' . $applicationPasswordOption),
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            'method' => 'POST',
            'body' => [
                'name' => $applicationPasswordOption,
            ],
        ]);
        $newPassword = json_decode((string)$response["body"], true, 512, JSON_THROW_ON_ERROR)['password'];
        update_option($applicationPasswordOption, $user_id);
        $this->create_update_website($user_id, $newPassword);
        return $newPassword;
    }

    private function create_update_website(string $user_id, string $password): bool
    {
        $user = get_user_by('id', $user_id);
        $url = parse_url(get_site_url());
        $stdClass = SageGraphQl::addUpdateWebsite(
            name: get_bloginfo(),
            username: $user->data->user_login,
            password: $password,
            websiteEnum: WebsiteEnum::Wordpress,
            host: $url["host"],
            protocol: $url["scheme"],
        );
        if (!is_null($stdClass)) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="notice notice-success is-dismissible"><p><?=
                        __('Successfully connected to API', 'sage')
                        ?></p></div>
                <?php
            });
            return true;
        }

        return false;
    }

    /**
     * Register plugin settings
     */
    public function register_settings(): void
    {
        // Check posted/selected tab.
        $current_section = '';
        if (isset($_POST['tab']) && $_POST['tab']) {
            $current_section = $_POST['tab'];
        } elseif (isset($_GET['tab']) && $_GET['tab']) {
            $current_section = $_GET['tab'];
        }

        foreach ($this->settings as $section => $data) {

            if ($current_section && $current_section !== $section) {
                continue;
            }

            // Add section to page.
            add_settings_section($section, $data['title'], function (array $section): void {
                $this->settings_section($section);
            }, Sage::$_token . '_settings');

            foreach ($data['fields'] as $field) {

                // Validation callback for field.
                $validation = '';
                if (isset($field['callback'])) {
                    $validation = $field['callback'];
                }

                // Register field.
                $option_name = self::$base . $field['id'];
                register_setting(Sage::$_token . '_settings', $option_name, $validation);

                // Add field to page.
                add_settings_field(
                    $field['id'],
                    $field['label'],
                    function (...$args): void {
                        $this->sage->admin->display_field(...$args);
                    },
                    Sage::$_token . '_settings',
                    $section,
                    ['field' => $field, 'prefix' => self::$base]
                );
            }

            if (!$current_section) {
                break;
            }
        }
    }

    /**
     * Settings section.
     *
     * @param array $section Array of section ids.
     */
    public function settings_section(array $section): void
    {
        $html = '<p>' . $this->settings[$section['id']]['description'] . '</p>' . "\n";
        echo $html;
    }

    /**
     * Add settings page to admin menu
     */
    public function add_menu_item(): void
    {

        $args = $this->menu_settings();

        foreach ($args as $arg) {
            // Do nothing if wrong location key is set.
            if (is_array($arg) && isset($arg['location']) && function_exists('add_' . $arg['location'] . '_page')) {
                switch ($arg['location']) {
                    case 'options':
                    case 'submenu':
                        $page = add_submenu_page(
                            $arg['parent_slug'],
                            $arg['page_title'],
                            $arg['menu_title'],
                            $arg['capability'],
                            $arg['menu_slug'],
                            $arg['function'],
                        );
                        break;
                    case 'menu':
                        $page = add_menu_page(
                            $arg['page_title'],
                            $arg['menu_title'],
                            $arg['capability'],
                            $arg['menu_slug'],
                            $arg['function'],
                            $arg['icon_url'],
                            $arg['position'],
                        );
                        break;
                    default:
                        return;
                }

                add_action('admin_print_styles-' . $page, function (): void {
                    $this->settings_assets();
                });
            }
        }
    }

    /**
     * Prepare default settings page arguments
     *
     * @return mixed|void
     */
    private function menu_settings()
    {
        return apply_filters(
            self::$base . 'menu_settings',
            [
                [
                    'location' => 'menu',
                    // Possible settings: options, menu, submenu.
                    'page_title' => __('Sage', 'sage'),
                    'menu_title' => __('Sage', 'sage'),
                    'capability' => self::$capability,
                    'menu_slug' => Sage::$_token . '_settings',
                    'function' => null,
                    'icon_url' => 'dashicons-rest-api',
                    'position' => 55.5,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::$_token . '_settings',
                    'page_title' => __('Settings', 'sage'),
                    'menu_title' => __('Settings', 'sage'),
                    'capability' => self::$capability,
                    'menu_slug' => Sage::$_token . '_settings',
                    'function' => function (): void {
                        $this->settings_page();
                    },
                    'position' => null,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::$_token . '_settings',
                    'page_title' => __('Clients', 'sage'),
                    'menu_title' => __('Clients', 'sage'),
                    'capability' => self::$capability,
                    'menu_slug' => Sage::$_token . '_fComptet',
                    'function' => function (): void {
                        $mandatoryField = 'ctNum';
                        $rawFields = get_option(SageSettings::$base . 'fComptet_fields') ?? self::$defaultFComptetFields;
                        $showCtNumField = in_array($mandatoryField, $rawFields);
                        if (!$showCtNumField) {
                            $rawFields[] = $mandatoryField;
                        }
                        $fields = [];
                        foreach (SageGraphQl::getTypeFilter('FComptetFilterInput')?->data?->__type?->inputFields as $inputField) {
                            if (in_array($inputField->name, $rawFields)) {
                                $fields[] = [
                                    'name' => $inputField->name,
                                    'type' => $inputField->type->name,
                                    'transDomain' => SageTranslationUtils::TRANS_FCOMPTETS,
                                ];
                            }
                        }
                        $queryParams = $_GET;
                        if (!isset($queryParams['per_page'])) {
                            $queryParams['per_page'] = get_option(SageSettings::$base . 'fComptet_perPage') ?? (string)self::$defaultPagination;
                        }
                        echo $this->sage->twig->render('fComptet/index.html.twig', [
                            'queryParams' => $queryParams,
                            'fComptets' => json_decode(json_encode(SageGraphQl::searchEntities('fComptets', $queryParams, $fields)), true),
                            'fields' => $fields,
                            'hideField' => $showCtNumField ? null : $mandatoryField,
                        ]);
                    },
                    'position' => null,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::$_token . '_settings',
                    'page_title' => __('About', 'sage'),
                    'menu_title' => __('About', 'sage'),
                    'capability' => self::$capability,
                    'menu_slug' => Sage::$_token . '_about',
                    'function' => function (): void {
                        echo 'about page';
                    },
                    'position' => null,
                ],
            ]
        );
    }

    /**
     * Load settings page content.
     */
    private function settings_page(): void
    {

        // Build page HTML.
        $html = $this->sage->twig->render('common/translations.html.twig');
        $html .= '<div class="wrap" id="' . Sage::$_token . '_settings">' . "\n";
        $html .= '<h2>' . __('Sage', 'sage') . '</h2>' . "\n";

        $tab = '';
        if (isset($_GET['tab']) && $_GET['tab']) {
            $tab .= $_GET['tab'];
        }

        // Show page tabs.
        if (1 < count($this->settings)) {

            $html .= '<h2 class="nav-tab-wrapper">' . "\n";

            $c = 0;
            foreach ($this->settings as $section => $data) {

                // Set tab class.
                $class = 'nav-tab';
                if (!isset($_GET['tab'])) {
                    if (0 === $c) {
                        $class .= ' nav-tab-active';
                    }
                } elseif ($section == $_GET['tab']) {
                    $class .= ' nav-tab-active';
                }

                // Set tab link.
                $tab_link = add_query_arg(['tab' => $section]);
                if (isset($_GET['settings-updated'])) {
                    $tab_link = remove_query_arg('settings-updated', $tab_link);
                }

                // Output tab.
                $html .= '<a href="' . $tab_link . '" class="' . esc_attr($class) . '">' . esc_html($data['title']) . '</a>' . "\n";

                ++$c;
            }

            $html .= '</h2>' . "\n";
        }

        $html .= '<form method="post" id="form_settings_sage" action="options.php" enctype="multipart/form-data">';

        // Get settings fields.
        ob_start();
        settings_fields(Sage::$_token . '_settings');
        do_settings_sections(Sage::$_token . '_settings');
        $html .= ob_get_clean();

        $html .= '<p class="submit">' . "\n";
        $html .= '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />' . "\n";
        $html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr(__('Save Settings', 'sage')) . '" />' . "\n";
        $html .= '</p>' . "\n";
        $html .= '</form>' . "\n";
        $html .= '</div>' . "\n";

        echo $html;
    }

    /**
     * Load settings JS & CSS
     */
    public function settings_assets(): void
    {

        // We're including the farbtastic script & styles here because they're needed for the colour picker
        // If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
        wp_enqueue_style('farbtastic');
        wp_enqueue_script('farbtastic');

        // We're including the WP media scripts here because they're needed for the image upload field.
        // If you're not including an image upload then you can leave this function call out.
        wp_enqueue_media();

        wp_register_script(Sage::$_token . '-settings-js', $this->sage->assets_url . 'js/settings' . $this->sage->script_suffix . '.js', ['farbtastic', 'jquery'], '1.0.0', true);
        wp_enqueue_script(Sage::$_token . '-settings-js');
    }

    /**
     * Add settings link to plugin list table
     *
     * @param array $links Existing links.
     * @return array        Modified links.
     */
    public function add_settings_link(array $links): array
    {
        $settings_link = '<a href="options-general.php?page=' . Sage::$_token . '_settings">' . __('Settings', 'sage') . '</a>';
        $links[] = $settings_link;
        return $links;
    }

    /**
     * Container for settings page arguments
     *
     * @param array $settings Settings array.
     */
    public function configure_settings(array $settings = []): array
    {
        return $settings;
    }

    /**
     * Main SageSettings Instance
     *
     * Ensures only one instance of SageSettings is loaded or can be loaded.
     *
     * @param Sage $sage Object instance.
     * @return self|null SageSettings instance
     * @see sage()
     */
    public static function instance(Sage $sage): ?self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($sage);
        }

        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of sage_API is forbidden.')), esc_attr($this->sage->_version));
    }

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of sage_API is forbidden.')), esc_attr($this->sage->_version));
    }
}
