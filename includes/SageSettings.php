<?php

namespace App;

use App\class\SageEntityMenu;
use App\class\SageEntityMetadata;
use App\enum\WebsiteEnum;
use App\lib\SageRequest;
use App\Utils\SageTranslationUtils;
use DateTime;
use PHPHtmlParser\Dom;
use stdClass;
use WC_Product;
use WP_Application_Passwords;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class.
 */
final class SageSettings
{
    public final const PREFIX_META_DATA = 'metaData';
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
                options: [],
                actions: [
                    'import_from_sage' => static function (array $data) use ($sageSettings): string {
                        $ctNum = $data['ctNum'];
                        $fComptet = $sageSettings->sage->sageGraphQl->getFComptet($ctNum);
                        if (is_null($fComptet)) {
                            return "<div class='error'>
                        " . __("L'utilisateur n'a pas pu être importé", 'sage') . "
                                </div>";
                        }
                        $userId = $sageSettings->sage->getUserIdWithCtNum($ctNum);
                        $user = $sageSettings->sage->sageWoocommerce->convertSageUserToWoocommerce(
                            $fComptet,
                            $userId,
                            current(array_filter($sageSettings->sageEntityMenus,
                                static fn(SageEntityMenu $sageEntityMenu) => $sageEntityMenu->getMetaKeyIdentifier() === Sage::META_KEY_CT_NUM
                            ))
                        );
                        if (is_string($user)) {
                            return $user;
                        }
                        $url = '/wp-json/wp/v2/users';
                        if (!is_null($userId)) {
                            $url .= '/' . $userId;
                        }
                        [$response, $responseError] = $sageSettings->createResource($url, is_null($userId) ? 'POST' : 'PUT', $user);

                        if (is_string($responseError)) {
                            return $responseError;
                        }
                        if ($response["response"]["code"] === 200) {
                            return "<div class='notice notice-success'>
                        " . __('User updated', 'sage') . "
                                </div>";
                        }
                        return "<div class='notice notice-success'>
                        " . __('User created', 'sage') . "
                                </div>";
                    }
                ],
                metadata: [
                    new SageEntityMetadata(field: '_ctNum', value: static function (StdClass $fComptet) {
                        return $fComptet->ctNum;
                    }),
                    new SageEntityMetadata(field: '_nCatTarif', value: static function (StdClass $fComptet) {
                        return $fComptet->nCatTarif;
                    }),
                    new SageEntityMetadata(field: '_last_update', value: static function (StdClass $fComptet) {
                        return (new DateTime())->format('Y-m-d H:i:s');
                    }),
                ],
                metaKeyIdentifier: Sage::META_KEY_CT_NUM,
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
                options: [],
                actions: [],
                metadata: [],
                metaKeyIdentifier: '',
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
                options: [
                    // exemple
//                    [
//                        'id' => SageEntityMenu::FARTICLE_ENTITY_NAME . '_something',
//                        'label' => __('The label', 'sage'),
//                        'description' => __('Description.', 'sage'),
//                        'type' => 'checkbox',
//                        'default' => ''
//                    ],
                ],
                actions: [
                    'import_from_sage' => function (array $data) use ($sageSettings): string {
                        $arRef = $data['arRef'];
                        $fArticle = $sageSettings->sage->sageGraphQl->getFArticle($arRef);
                        if (is_null($fArticle)) {
                            return "<div class='error'>
                        " . __("L'article n'a pas pu être importé", 'sage') . "
                                </div>";
                        }
                        $articleId = $sageSettings->sage->sageWoocommerce->getWooCommerceIdArticle($arRef);
                        $article = $sageSettings->sage->sageWoocommerce->convertSageArticleToWoocommerce($fArticle,
                            current(array_filter($sageSettings->sageEntityMenus,
                                static fn(SageEntityMenu $sageEntityMenu) => $sageEntityMenu->getMetaKeyIdentifier() === Sage::META_KEY_AR_REF
                            ))
                        );
                        $url = '/wp-json/wc/v3/products';
                        if (!is_null($articleId)) {
                            $url .= '/' . $articleId;
                        }
                        [$response, $responseError] = $sageSettings->createResource($url, is_null($articleId) ? 'POST' : 'PUT', $article);
                        if (is_string($responseError)) {
                            return $responseError;
                        }
                        if ($response["response"]["code"] === 200) {
                            return "<div class='notice notice-success'>
                        " . __('Article updated', 'sage') . "
                                </div>";
                        }
                        return "<div class='notice notice-success'>
                        " . __('Article created', 'sage') . "
                                </div>";
                    }
                ],
                metadata: [
                    new SageEntityMetadata(field: '_arRef', value: static function (StdClass $fArticle) {
                        return $fArticle->arRef;
                    }),
                    new SageEntityMetadata(field: '_prices', value: static function (StdClass $fArticle) {
                        return $fArticle->prices;
                    }),
                    new SageEntityMetadata(field: '_max_price', value: static function (StdClass $fArticle) {
                        $prices = json_decode($fArticle->prices, true, 512, JSON_THROW_ON_ERROR);
                        usort($prices, static function (array $a, array $b) {
                            return $b['PriceTtc'] <=> $a['PriceTtc'];
                        });
                        return json_encode($prices[0]);
                    }),
                    new SageEntityMetadata(field: '_last_update', value: static function (StdClass $fArticle) {
                        return (new DateTime())->format('Y-m-d H:i:s');
                    }),
                ],
                metaKeyIdentifier: Sage::META_KEY_AR_REF,
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
                $options = [
                    [
                        'id' => $sageEntityMenu->getEntityName() . '_fields',
                        'label' => __('Fields to show', 'sage'),
                        'description' => __('Please select the fields to show on the table.', 'sage'),
                        'type' => '2_select_multi',
                        'options' => $this->getFieldsForEntity($sageEntityMenu),
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
                    ...$sageEntityMenu->getOptions(),
                ];
                $sageEntityMenu->setOptions($options);
                $settings[$sageEntityMenu->getEntityName()] = [
                    'title' => __($sageEntityMenu->getTitle(), 'sage'),
                    'description' => $sageEntityMenu->getDescription(),
                    'fields' => $options,
                ];
            }

            $this->settings = apply_filters(Sage::TOKEN . '_settings_fields', $settings);
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
                            $this->sage->admin->display_field(...$args);
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
        });

        // Add settings page to menu.
        add_action('admin_menu', function () use ($sageSettings): void {
            $args = apply_filters(
                Sage::TOKEN . '_menu_settings',
                [
                    [
                        'location' => 'menu',
                        // Possible settings: options, menu, submenu.
                        'page_title' => __('Sage', 'sage'),
                        'menu_title' => __('Sage', 'sage'),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::TOKEN . '_settings',
                        'function' => null,
                        'icon_url' => 'dashicons-rest-api',
                        'position' => 55.5,
                    ],
                    [
                        'location' => 'submenu',
                        // Possible settings: options, menu, submenu.
                        'parent_slug' => Sage::TOKEN . '_settings',
                        'page_title' => __('Settings', 'sage'),
                        'menu_title' => __('Settings', 'sage'),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::TOKEN . '_settings',
                        'function' => function (): void {
                            // todo use twig
                            // Build page HTML.
                            $html = $this->sage->twig->render('base.html.twig');
                            $html .= '<div class="wrap" id="' . Sage::TOKEN . '_settings">' . "\n";
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
                            settings_fields(Sage::TOKEN . '_settings');
                            do_settings_sections(Sage::TOKEN . '_settings');
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
                        'parent_slug' => Sage::TOKEN . '_settings',
                        'page_title' => __($sageEntityMenu->getTitle(), 'sage'),
                        'menu_title' => __($sageEntityMenu->getTitle(), 'sage'),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::TOKEN . '_' . $sageEntityMenu->getEntityName(),
                        'function' => static function () use ($sageSettings, $sageEntityMenu): void {
                            $queryParams = $_GET;
                            if (array_key_exists('action', $queryParams)) {
                                $action = json_decode(stripslashes((string)$queryParams['action']), true, 512, JSON_THROW_ON_ERROR);
                                $message = $sageEntityMenu->getActions()[$action["type"]]($action["data"]);
                                $redirect = remove_query_arg('action', wp_get_referer());
                                $redirect = add_query_arg(Sage::TOKEN . '_message', urlencode($message), $redirect);
                                wp_redirect($redirect);
                                exit;
                            }

                            $rawFields = get_option(Sage::TOKEN . '_' . $sageEntityMenu->getEntityName() . '_fields');
                            if ($rawFields === false) {
                                $rawFields = $sageEntityMenu->getDefaultFields();
                            }

                            $mandatoryFields = $sageEntityMenu->getMandatoryFields();
                            $hideFields = [...array_diff($mandatoryFields, $rawFields), SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_postId'];
                            $rawFields = array_unique([...$rawFields, ...$hideFields]);
                            $fields = [];
                            $inputFields = $sageSettings->sage->sageGraphQl->getTypeFilter($sageEntityMenu->getFilterType()) ?? [];
                            $transDomain = $sageEntityMenu->getTransDomain();
                            foreach (
                                array_unique([...$rawFields, ...$mandatoryFields])
                                as $rawField) {
                                if (array_key_exists($rawField, $inputFields)) {
                                    $fields[] = [
                                        'name' => $inputFields[$rawField]->name,
                                        'type' => $inputFields[$rawField]->type->name,
                                        'transDomain' => $transDomain,
                                    ];
                                } else {
                                    $fields[] = [
                                        'name' => $rawField,
                                        'type' => 'StringOperationFilterInput',
                                        'transDomain' => $transDomain,
                                    ];
                                }
                            }

                            if (!isset($queryParams['per_page'])) {
                                $queryParams['per_page'] = get_option(Sage::TOKEN . '_' . $sageEntityMenu->getEntityName() . '_perPage');
                                if ($queryParams['per_page'] === false) {
                                    $queryParams['per_page'] = (string)self::$defaultPagination;
                                }
                            }

                            $data = json_decode(json_encode($sageSettings->sage->sageGraphQl
                                ->searchEntities($sageEntityMenu->getEntityName(), $queryParams, $fields)
                                , JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                            $data = $sageSettings->sage->sageWoocommerce->populateMetaDatas($data, $fields, $sageEntityMenu);
                            $hideFields = array_map(static function (string $hideField) {
                                return str_replace(SageSettings::PREFIX_META_DATA, '', $hideField);
                            }, $hideFields);
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
                        'parent_slug' => Sage::TOKEN . '_settings',
                        'page_title' => __('About', 'sage'),
                        'menu_title' => __('About', 'sage'),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::TOKEN . '_about',
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

                        wp_register_script(Sage::TOKEN . '-settings-js', $this->sage->assets_url . 'js/settings' . $this->sage->script_suffix . '.js', ['farbtastic', 'jquery'], '1.0.0', true);
                        wp_enqueue_script(Sage::TOKEN . '-settings-js');
                    });
                }
            }
        });

        // Add settings link to plugins page.
        add_filter(
            'plugin_action_links_' . plugin_basename($this->sage->file),
            static function (array $links): array {
                $links[] = '<a href="options-general.php?page=' . Sage::TOKEN . '_settings">' . __('Settings', 'sage') . '</a>';
                return $links;
            }
        );

        // Configure placement of plugin settings page. See readme for implementation.
        add_filter(Sage::TOKEN . '_menu_settings', static fn(array $settings = []): array => $settings);

        // region WooCommerce
        add_action('add_meta_boxes', static function (): void { // remove [Product type | virtual | downloadable] add product arRef
            $arRef = Sage::getArRef(get_the_ID());
            if (empty($arRef)) {
                return;
            }

            global $wp_meta_boxes;
            $id = 'woocommerce-product-data';
            $screen = 'product';
            $context = 'normal';
            $callback = $wp_meta_boxes[$screen][$context]["high"][$id]["callback"];
            remove_meta_box($id, $screen, $context);
            add_meta_box($id, __('Product data', 'woocommerce'), static function (WP_Post $wpPost) use ($arRef, $callback): void {
                ob_start();
                $callback($wpPost);
                $dom = new Dom(); // https://github.com/paquettg/php-html-parser?tab=readme-ov-file#modifying-the-dom
                $dom->loadStr(ob_get_clean());

                $a = $dom->find('span.product-data-wrapper')[0];
                echo str_replace($a->innerHtml(), ': <span style="display: initial" class="h4">' . $arRef . '</span>', $dom);
            }, 'product', 'normal', 'high');
        }, 40); // woocommerce/includes/admin/class-wc-admin-meta-boxes.php => 40 > 30 : add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );

        // region Custom Product Tabs In WooCommerce https://aovup.com/woocommerce/add-tabs/
        $productTabs = [
            ['name' => 'general', 'trans' => __('General', 'woocommerce')],
            ['name' => 'inventory', 'trans' => __('Inventory', 'woocommerce')],
            ['name' => 'shipping', 'trans' => __('Shipping', 'woocommerce')],
//            ['name' => 'linked_product', 'trans' => __('Linked Products', 'woocommerce')],
//            ['name' => 'attribute', 'trans' => __('Attributes', 'woocommerce')],
            ['name' => 'variations', 'trans' => __('Variations', 'woocommerce')],
//            ['name' => 'advanced', 'trans' => __('Advanced', 'woocommerce')],
        ];
        add_filter('woocommerce_product_data_tabs', static function (array $tabs) use ($productTabs) { // Code to Create Tab in the Backend
            $arRef = Sage::getArRef(get_the_ID());
            if (empty($arRef)) {
                return $tabs;
            }

            foreach ($productTabs as $productTab) {
                $tabs[$productTab['name']] = [
                    ...$tabs[$productTab['name']],
                    'label' => $productTab['trans'],
                    'target' => Sage::TOKEN . '_product_data_panel_' . $productTab['name'],
                ];
            }

            return $tabs;
        });

        add_action('woocommerce_product_data_panels', static function () use ($sageSettings, $productTabs): void { // Code to Add Data Panel to the Tab
            $product = wc_get_product();
            if (!($product instanceof WC_Product)) {
                return;
            }
            $pCattarifs = $sageSettings->sage->sageGraphQl->getPCattarifs();
            echo $sageSettings->sage->twig->render('woocommerce/tabs.html.twig', [
                'tabNames' => array_map(static fn(array $productTab): string => $productTab['name'], $productTabs),
                'product' => $product,
                'pCattarifs' => $pCattarifs,
            ]);
        });
        // endregion
        // endregion

        // region user meta
        $userMetaProp = 'customMeta';
        add_filter('rest_pre_insert_user', static function (
            stdClass        $prepared_user,
            WP_REST_Request $request
        ) use ($userMetaProp): stdClass {
            if (!empty($request['meta'])) {
                $prepared_user->{$userMetaProp} = [];
                foreach ($request['meta'] as $key => $value) {
                    $prepared_user->{$userMetaProp}[$key] = $value;
                }
            }
            return $prepared_user;
        }, accepted_args: 2);
        add_filter('insert_custom_user_meta', static function (
            array   $custom_meta,
            WP_User $user,
            bool    $update,
            array   $userdata
        ) use ($userMetaProp): array {
            if (array_key_exists($userMetaProp, $userdata)) {
                foreach ($userdata[$userMetaProp] as $key => $value) {
                    $custom_meta[$key] = $value;
                }
            }
            return $custom_meta;
        }, accepted_args: 4);
        // endregion
    }

    private function createResource(string $url, string $method, array $body): array
    {
        $response = SageRequest::selfRequest($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'method' => $method,
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $responseError = null;
        if ($response instanceof WP_Error) {
            $responseError = "<div class=error>
                                <pre>" . $response->get_error_code() . "</pre>
                                <pre>" . $response->get_error_message() . "</pre>
                                </div>";
        }

        if (!in_array($response["response"]["code"], [200, 201], true)) {
            $responseError = "<div class=error>
                                <pre>" . $response['response']['code'] . "</pre>
                                <pre>" . $response['body'] . "</pre>
                                </div>";
        }
        return [$response, $responseError];
    }

    private function getFieldsForEntity(SageEntityMenu $sageEntityMenu): array
    {
        $transDomain = $sageEntityMenu->getTransDomain();
        $typeModel = $this->sage->sageGraphQl->getTypeModel($sageEntityMenu->getTypeModel());
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

        // region custom meta fields
        $prefix = self::PREFIX_META_DATA . '_' . Sage::TOKEN;
        foreach ($sageEntityMenu->getMetadata() as $metadata) {
            $fieldName = $prefix . $metadata->getField();
            $objectFields[$fieldName] = $trans[$transDomain][$fieldName];
        }
        // endregion

        return $objectFields;
    }

    private function addWebsiteSageApi(): void
    {
        if (
            !(
                array_key_exists('settings-updated', $_GET) &&
                array_key_exists('page', $_GET) &&
                $_GET["settings-updated"] === 'true' &&
                $_GET["page"] === Sage::TOKEN . '_settings'
            ) ||
            !current_user_can(self::$capability)
        ) {
            return;
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
        $url = parse_url((string)get_option(Sage::TOKEN . '_wordpress_host_url'));
        global $wpdb;
        $stdClass = $this->sage->sageGraphQl->addUpdateWebsite(
            name: get_bloginfo(),
            username: $user->data->user_login,
            password: $password,
            websiteEnum: WebsiteEnum::Wordpress,
            host: $url["host"],
            protocol: $url["scheme"],
            forceSsl: (bool)get_option(Sage::TOKEN . '_activate_https_verification_wordpress'),
            dbHost: get_option(Sage::TOKEN . '_wordpress_db_host'),
            tablePrefix: $wpdb->prefix,
            dbName: get_option(Sage::TOKEN . '_wordpress_db_name'),
            dbUsername: get_option(Sage::TOKEN . '_wordpress_db_username'),
            dbPassword: get_option(Sage::TOKEN . '_wordpress_db_password'),
            syncArticlesToWebsite: (bool)get_option(Sage::TOKEN . '_sync_articles_to_website'),
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
