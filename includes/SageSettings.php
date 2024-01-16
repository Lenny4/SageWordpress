<?php

namespace App;

use App\class\SageEntityMenu;
use App\enum\WebsiteEnum;
use App\lib\SageRequest;
use App\Utils\SageTranslationUtils;
use Exception;
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
    public static string $capability = 'manage_options';

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
     * @var SageEntityMenu[]
     */
    private readonly array $sageEntityMenus;

    /**
     * Constructor function.
     *
     * @param Sage|null $sage Parent object.
     */
    private function __construct(public ?Sage $sage)
    {
        $sageSettings = $this;
        $this->sageEntityMenus = [
            new SageEntityMenu(
                title: 'Clients',
                description: __("Gestion des clients.", 'sage'),
                entityName: SageEntityMenu::FCOMPTET_ENTITY_NAME,
                typeModel: SageEntityMenu::FCOMPTET_TYPE_MODEL,
                defaultSortField: 'ctNum',
                defaultFields: SageEntityMenu::FCOMPTET_DEFAULT_FIELDS,
                mandatoryFields: ['ctNum'],
                filterType: SageEntityMenu::FCOMPTET_FILTER_TYPE,
                transDomain: SageTranslationUtils::TRANS_FCOMPTETS,
                fields: [],
                actions: [
                    SageEntityMenu::FCOMPTET_ENTITY_NAME . '_import_from_sage' => static function (array $data): void {
                        $ctNum = $data['ctNum'];
                        // todo add user in wordpress
                        $todo = 0;
                    }
                ],
            ),
            new SageEntityMenu(
                title: 'Documents',
                description: __("Gestion Commerciale / Menu Traitement / Documents des ventes, des achats, des stocks et internes / Fenêtre Document", 'sage'),
                entityName: SageEntityMenu::FDOCENTETE_ENTITY_NAME,
                typeModel: SageEntityMenu::FDOCENTETE_TYPE_MODEL,
                defaultSortField: 'doPiece',
                defaultFields: SageEntityMenu::FDOCENTETE_DEFAULT_FIELDS,
                mandatoryFields: ['doPiece', 'doType'],
                filterType: SageEntityMenu::FDOCENTETE_FILTER_TYPE,
                transDomain: SageTranslationUtils::TRANS_FDOCENTETES,
                fields: [],
                actions: [],
            ),
            new SageEntityMenu(
                title: 'Articles',
                description: __("Gestion des articles", 'sage'),
                entityName: SageEntityMenu::FARTICLE_ENTITY_NAME,
                typeModel: SageEntityMenu::FARTICLE_TYPE_MODEL,
                defaultSortField: 'arRef',
                defaultFields: SageEntityMenu::FARTICLE_DEFAULT_FIELDS,
                mandatoryFields: ['arRef'],
                filterType: SageEntityMenu::FARTICLE_FILTER_TYPE,
                transDomain: SageTranslationUtils::TRANS_FARTICLES,
                fields: [
                    // exemple
//                    [
//                        'id' => SageEntityMenu::FARTICLE_ENTITY_NAME . '_something',
//                        'label' => __('The label', 'sage'),
//                        'description' => __('Description.', 'sage'),
//                        'type' => 'checkbox',
//                        'default' => ''
//                    ],
                ],
                actions: [],
            ),
        ];
        // Initialise settings.
        add_action('init', function (): void {
            $url = parse_url(get_site_url());
            $defaultWordpressUrl = $url["scheme"] . '://' . $url["host"];
            global $wpdb;
            $settings = [
                'api' => [
                    'title' => __('Api', 'sage'),
                    'description' => __('These are fairly standard form input fields.', 'sage'),
                    'fields' => [
                        [
                            'id' => 'api_key',
                            'label' => __('Api key', 'sage'),
                            'description' => __('Can be found here.', 'sage'),
                            'type' => 'text',
                            'default' => '',
                            'placeholder' => __('XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX', 'sage')
                        ],
                        [
                            'id' => 'api_host_url',
                            'label' => __('Api host url', 'sage'),
                            'description' => __('Can be found here.', 'sage'),
                            'type' => 'text',
                            'default' => '',
                            'placeholder' => __('https://192.168.0.1', 'sage')
                        ],
                        [
                            'id' => 'activate_https_verification_graphql',
                            'label' => __('Activer Https GraphQl', 'sage'),
                            'description' => __("Décochez cette case si vous avez l'erreur: cURL error 60: SSL certificate problem: self-signed certificate.", 'sage'),
                            'type' => 'checkbox',
                            'default' => 'on'
                        ],
                        [
                            'id' => 'wordpress_host_url',
                            'label' => __('Wordpress host url', 'sage'),
                            'description' => __('Renseigner l\'url à laquelle l\'API Sage peut contacter l\'API de wordpress.', 'sage'),
                            'type' => 'text',
                            'default' => $defaultWordpressUrl,
                            'placeholder' => __($defaultWordpressUrl, 'sage')
                        ],
                        [
                            'id' => 'sync_articles_to_website',
                            'label' => __('Synchronise les articles de Sage dans Wordpress', 'sage'),
                            'description' => __('Tous les articles dans Sage sont crées automatiquement dans WooCommerce.', 'sage'),
                            'type' => 'checkbox',
                            'default' => ''
                        ],
                        [
                            'id' => 'activate_https_verification_wordpress',
                            'label' => __('Activer Https Wordpress', 'sage'),
                            'description' => __("Décochez cette case si vous avez l'erreur: <br>The SSL connection could not be established, see inner exception.", 'sage'),
                            'type' => 'checkbox',
                            'default' => 'on'
                        ],
                        [
                            'id' => 'wordpress_db_host',
                            'label' => __('Wordpress db host', 'sage'),
                            'description' => __('Renseigner l\'IP à laquelle l\'API Sage peut contacter la base de données de wordpress.', 'sage'),
                            'type' => 'text',
                            'default' => $wpdb->dbhost,
                            'placeholder' => __($wpdb->dbhost, 'sage')
                        ],
                        [
                            'id' => 'wordpress_db_name',
                            'label' => __('Wordpress database name', 'sage'),
                            'description' => __('Renseigner le nom de la base de données de wordpress.', 'sage'),
                            'type' => 'text',
                            'default' => $wpdb->dbname,
                            'placeholder' => __($wpdb->dbname, 'sage')
                        ],
                        [
                            'id' => 'wordpress_db_username',
                            'label' => __('Wordpress database username', 'sage'),
                            'description' => __('Renseigner le nom de l\'utilisateur de la base de données de wordpress.', 'sage'),
                            'type' => 'text',
                            'default' => $wpdb->dbuser,
                            'placeholder' => __($wpdb->dbuser, 'sage')
                        ],
                        [
                            'id' => 'wordpress_db_password',
                            'label' => __('Wordpress database password', 'sage'),
                            'description' => __('Renseigner le mot de passe de la base de données de wordpress.', 'sage'),
                            'type' => 'text',
                            'default' => $wpdb->dbpassword,
                            'placeholder' => __($wpdb->dbpassword, 'sage')
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
            ];
            foreach ($this->sageEntityMenus as $sageEntityMenu) {
                $fields = [
                    ...[
                        [
                            'id' => $sageEntityMenu->getEntityName() . '_fields',
                            'label' => __('Fields to show', 'sage'),
                            'description' => __('Please select the fields to show on the table.', 'sage'),
                            'type' => '2_select_multi',
                            'options' => $this->getFieldsForEntity($sageEntityMenu->getTypeModel(), $sageEntityMenu->getTransDomain()),
                            'default' => $sageEntityMenu->getDefaultFields(),
                        ],
                        [
                            'id' => $sageEntityMenu->getEntityName() . '_perPage',
                            'label' => __('Default per page', 'sage'),
                            'description' => __('Please select the number of rows to show on the table.', 'sage'),
                            'type' => 'select',
                            'options' => array_combine(self::$paginationRange, self::$paginationRange),
                            'default' => (string)self::$defaultPagination
                        ],
                    ],
                    ...$sageEntityMenu->getFields(),
                ];
                $sageEntityMenu->setFields($fields);
                $settings[$sageEntityMenu->getEntityName()] = [
                    'title' => __($sageEntityMenu->getTitle(), 'sage'),
                    'description' => $sageEntityMenu->getDescription(),
                    'fields' => $fields,
                ];
            }

            $this->settings = apply_filters(Sage::$_token . '_settings_fields', $settings);
            $this->addWebsiteSageApi();
        }, 11);

        // Register plugin settings.
        add_action('admin_init', function (): void {
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
                    $html = '<p>' . $this->settings[$section['id']]['description'] . '</p>' . "\n";
                    echo $html;
                }, Sage::$_token . '_settings');

                foreach ($data['fields'] as $field) {

                    // Validation callback for field.
                    $validation = '';
                    if (isset($field['callback'])) {
                        $validation = $field['callback'];
                    }

                    // Register field.
                    $option_name = Sage::$_token . '_' . $field['id'];
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
                        ['field' => $field, 'prefix' => Sage::$_token . '_']
                    );
                }

                if (!$current_section) {
                    break;
                }
            }
        });

        // Add settings page to menu.
        add_action('admin_menu', function () use ($sageSettings): void {
            $args = apply_filters(
                Sage::$_token . '_menu_settings',
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
                            // todo use twig
                            // Build page HTML.
                            $html = $this->sage->twig->render('base.html.twig');
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
                        },
                        'position' => null,
                    ],
                    ...array_map(static fn(SageEntityMenu $sageEntityMenu): array => [
                        'location' => 'submenu',
                        // Possible settings: options, menu, submenu.
                        'parent_slug' => Sage::$_token . '_settings',
                        'page_title' => __($sageEntityMenu->getTitle(), 'sage'),
                        'menu_title' => __($sageEntityMenu->getTitle(), 'sage'),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::$_token . '_' . $sageEntityMenu->getEntityName(),
                        'function' => static function () use ($sageSettings, $sageEntityMenu): void {
                            $queryParams = $_GET;
                            if (array_key_exists('action', $queryParams)) {
                                $action = json_decode(stripslashes((string)$queryParams['action']), true, 512, JSON_THROW_ON_ERROR);
                                $sageEntityMenu->getActions()[$action["type"]]($action["data"]);
                                $goback = remove_query_arg('action', wp_get_referer());
                                wp_redirect($goback);
                                exit;
                            }

                            $rawFields = get_option(Sage::$_token . '_' . $sageEntityMenu->getEntityName() . '_fields');
                            if ($rawFields === false) {
                                $rawFields = $sageEntityMenu->getDefaultFields();
                            }

                            $hideFields = array_diff($sageEntityMenu->getMandatoryFields(), $rawFields);
                            $rawFields = array_unique([...$rawFields, ...$hideFields]);
                            $fields = [];
                            foreach ($sageSettings->sage->sageGraphQl->getTypeFilter($sageEntityMenu->getFilterType()) ?? [] as $inputField) {
                                if (in_array($inputField->name, $rawFields, true)) {
                                    $fields[] = [
                                        'name' => $inputField->name,
                                        'type' => $inputField->type->name,
                                        'transDomain' => $sageEntityMenu->getTransDomain(),
                                    ];
                                }
                            }

                            if (!isset($queryParams['per_page'])) {
                                $queryParams['per_page'] = get_option(Sage::$_token . '_' . $sageEntityMenu->getEntityName() . '_perPage');
                                if ($queryParams['per_page'] === false) {
                                    $queryParams['per_page'] = (string)self::$defaultPagination;
                                }
                            }

                            $data = json_decode(json_encode($sageSettings->sage->sageGraphQl->searchEntities($sageEntityMenu->getEntityName(), $queryParams, $fields), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                            if (
                                isset($data["data"][$sageEntityMenu->getEntityName()]["items"]) &&
                                !empty($items = $data["data"][$sageEntityMenu->getEntityName()]["items"]) &&
                                array_diff($sageEntityMenu->getMandatoryFields(), array_keys($items[0])) !== []
                            ) {
                                throw new Exception("Mandatory fields are missing");
                            }

                            echo $sageSettings->sage->twig->render('sage/' . $sageEntityMenu->getEntityName() . '/index.html.twig', [
                                'queryParams' => $queryParams,
                                'data' => $data,
                                'fields' => $fields,
                                'hideFields' => $hideFields,
                                'sageEntityMenu' => $sageEntityMenu,
                            ]);
                        },
                        'position' => null,
                    ],
                        $this->sageEntityMenus),
                    [
                        'location' => 'submenu',
                        // Possible settings: options, menu, submenu.
                        'parent_slug' => Sage::$_token . '_settings',
                        'page_title' => __('About', 'sage'),
                        'menu_title' => __('About', 'sage'),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::$_token . '_about',
                        'function' => static function (): void {
                            echo 'about page';
                        },
                        'position' => null,
                    ],
                ]
            );

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
                        // We're including the farbtastic script & styles here because they're needed for the colour picker
                        // If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
                        wp_enqueue_style('farbtastic');
                        wp_enqueue_script('farbtastic');

                        // We're including the WP media scripts here because they're needed for the image upload field.
                        // If you're not including an image upload then you can leave this function call out.
                        wp_enqueue_media();

                        wp_register_script(Sage::$_token . '-settings-js', $this->sage->assets_url . 'js/settings' . $this->sage->script_suffix . '.js', ['farbtastic', 'jquery'], '1.0.0', true);
                        wp_enqueue_script(Sage::$_token . '-settings-js');
                    });
                }
            }
        });

        // Add settings link to plugins page.
        add_filter(
            'plugin_action_links_' . plugin_basename($this->sage->file),
            static function (array $links): array {
                $links[] = '<a href="options-general.php?page=' . Sage::$_token . '_settings">' . __('Settings', 'sage') . '</a>';
                return $links;
            }
        );

        // Configure placement of plugin settings page. See readme for implementation.
        add_filter(Sage::$_token . '_menu_settings', static function (array $settings = []): array {
            return $settings;
        });

        // region Custom Product Tabs In WooCommerce https://aovup.com/woocommerce/add-tabs/
        add_filter('woocommerce_product_data_tabs', static function ($tabs) { // Code to Create Tab in the Backend
            $tabs['sage'] = [
                'label' => __('Sage', 'sage'),
                'target' => 'sage_product_data_panel',
                'priority' => 100,
            ];
            return $tabs;
        });

        add_action('woocommerce_product_data_panels', static function () use ($sageSettings) { // Code to Add Data Panel to the Tab
            $arRef = get_post_meta(get_the_ID(), '_' . Sage::$_token . '_tab_arRef', true);
            $fArticle = $sageSettings->sage->sageGraphQl->searchEntities(
                SageEntityMenu::FARTICLE_ENTITY_NAME,
                [
                    "filter_field" => [
                        "arRef"
                    ],
                    "filter_type" => [
                        "eq"
                    ],
                    "filter_value" => [
                        $arRef
                    ],
                    "paged" => "1",
                    "per_page" => "1"
                ],
                [
                    [
                        "name" => "arRef",
                        "type" => "StringOperationFilterInput",
                    ],
                    [
                        "name" => "arDesign",
                        "type" => "StringOperationFilterInput",
                    ]
                ]
            );
            echo $sageSettings->sage->twig->render('woocommerce/productDataPanels.html.twig', [
                'fArticle' => !is_null($fArticle) ? $fArticle->data->fArticles->items[0] : $fArticle,
            ]);
        });
        // endregion
    }

    private function getFieldsForEntity(string $object, string $transDomain): array
    {
        $typeModel = $this->sage->sageGraphQl->getTypeModel($object);
        if (!is_null($typeModel)) {
            $fieldsObject = array_filter($typeModel,
                static fn(stdClass $entity): bool => $entity->type->kind !== 'OBJECT' &&
                    $entity->type->kind !== 'LIST' &&
                    $entity->type->ofType?->kind !== 'LIST');
        } else {
            $fieldsObject = [];
        }
        $trans = SageTranslationUtils::getTranslations();
        $objectFields = [];
        foreach ($fieldsObject as $fieldObject) {
            $v = $trans[$transDomain][$fieldObject->name];
            $objectFields[$fieldObject->name] = $v['label'] ?? $v;
        }

        return $objectFields;
    }

    private function addWebsiteSageApi(): void
    {
        if (
            !(
                array_key_exists('settings-updated', $_GET) &&
                array_key_exists('page', $_GET) &&
                $_GET["settings-updated"] === 'true' &&
                $_GET["page"] === Sage::$_token . '_settings'
            ) ||
            !current_user_can(self::$capability)
        ) {
            return;
        }

        $applicationPasswordOption = Sage::$_token . '_application-passwords';
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
        }
    }

    private function isApiAuthenticated(): bool
    {
        $response = SageRequest::apiRequest('/Website/' . $_SERVER['HTTP_HOST'] . '/Authorization');
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
        $this->createUpdateWebsite($user_id, $newPassword);
        return $newPassword;
    }

    private function createUpdateWebsite(string $user_id, string $password): bool
    {
        $user = get_user_by('id', $user_id);
        $url = parse_url(get_option(Sage::$_token . '_wordpress_host_url'));
        global $wpdb;
        $stdClass = $this->sage->sageGraphQl->addUpdateWebsite(
            name: get_bloginfo(),
            username: $user->data->user_login,
            password: $password,
            websiteEnum: WebsiteEnum::Wordpress,
            host: $url["host"],
            protocol: $url["scheme"],
            forceSsl: (bool)get_option(Sage::$_token . '_activate_https_verification_wordpress'),
            dbHost: get_option(Sage::$_token . '_wordpress_db_host'),
            tablePrefix: $wpdb->prefix,
            dbName: get_option(Sage::$_token . '_wordpress_db_name'),
            dbUsername: get_option(Sage::$_token . '_wordpress_db_username'),
            dbPassword: get_option(Sage::$_token . '_wordpress_db_password'),
            syncArticlesToWebsite: (bool)get_option(Sage::$_token . '_sync_articles_to_website'),
        );
        if (!is_null($stdClass)) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="notice notice-success is-dismissible"><p><?=
                        __('Successfully connected to API.', 'sage')
                        ?></p></div>
                <?php
            });
            return true;
        }

        return false;
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
