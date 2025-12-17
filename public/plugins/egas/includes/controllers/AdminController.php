<?php

namespace App\controllers;

use App\class\SageExpectedOption;
use App\resources\ImportConditionDto;
use App\resources\Resource;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\TwigService;

class AdminController
{
    private static array|null $settings = null;

    public static function addSections(): void
    {
        $settings = self::getSettings();
        // Check posted/selected tab.
        $current_section = '';
        if (isset($_POST['tab']) && $_POST['tab']) {
            $current_section = $_POST['tab'];
        } elseif (isset($_GET['tab']) && $_GET['tab']) {
            $current_section = $_GET['tab'];
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
                $resource = null;
                if (isset($data["resource"])) {
                    $resource = $data["resource"];
                }
                // Add field to page.
                add_settings_field(
                    $field['id'],
                    $field['label'],
                    function (...$args) use ($resource): void {
                        AdminController::display_field(...$args, resource: $resource);
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

    private static function getSettings(): ?array
    {
        if (is_null(self::$settings)) {
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
                        [
                            'id' => 'nb_threads',
                            'label' => __("Nombre de d'opérations simultanées (nb threads)", Sage::TOKEN),
                            'description' => '',
                            'type' => 'number',
                            'default' => '1',
                            'placeholder' => __('1', Sage::TOKEN)
                        ],
                    ]
                ],
            ];
            $sageService = SageService::getInstance();
            foreach ($sageService->getResources() as $resource) {
                $fieldOptions = $sageService->getFieldsForEntity($resource);
                $defaultFields = $resource->getDefaultFields();
                $options = [
                    [
                        'id' => $resource->getEntityName() . '_show_fields',
                        'label' => __('Champs à montrer', Sage::TOKEN),
                        'description' => __('Veuillez sélectionner les champs à afficher sur le tableau.', Sage::TOKEN),
                        'type' => '2_select_multi',
                        'options' => $fieldOptions,
                        'default' => $defaultFields,
                    ],
                    [
                        'id' => $resource->getEntityName() . '_perPage',
                        'label' => __('Nombre d\'élément par défaut par page', Sage::TOKEN),
                        'description' => __('Veuillez sélectionner le nombre de lignes à afficher sur le tableau.', Sage::TOKEN),
                        'type' => 'select',
                        'options' => array_combine(Sage::$paginationRange, Sage::$paginationRange),
                        'default' => (string)Sage::$defaultPagination
                    ],
                    [
                        'id' => $resource->getEntityName() . '_filter_fields',
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
                    ...$resource->getOptions(),
                ];
                $resource->setOptions($options);
                $settings[$resource->getEntityName()] = [
                    'title' => __($resource->getTitle(), Sage::TOKEN),
                    'description' => $resource->getDescription(),
                    'fields' => $options,
                    'resource' => $resource,
                ];
            }
            self::$settings = $settings;
        }
        return self::$settings;
    }

    /**
     * Generate HTML for displaying fields.
     *
     * @param array $data Data array.
     * @param object|null $post Post object.
     * @param boolean $echo Whether to echo the field HTML or return it.
     */
    public static function display_field(array $data = [], object $post = null, bool $echo = true, ?Resource $resource = null): string
    {

        // Get field info.
        $field = $data['field'] ?? $data;

        // Check for prefix on option name.
        $option_name = '';
        if (isset($data['prefix'])) {
            $option_name = $data['prefix'];
        }

        // Get saved data.
        $data = '';
        $option_name .= $field['id'];
        if ($post !== null) {

            // Get saved field data.
            $option = get_post_meta($post->ID, $field['id'], true);

            // Get data to display in field.
        } else {

            // Get saved option.
            $option = get_option($option_name);

            // Get data to display in field.
        }

        if (isset($option)) {
            $data = $option;
        }

        // Show default data if no option saved and default is supplied.
        if (false === $data && isset($field['default'])) {
            $data = $field['default'];
        } elseif (false === $data) {
            $data = '';
        }

        $html = '';

        switch ($field['type']) {

            case 'text':
            case 'url':
            case 'email':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '" />' . "\n";
                break;
            case 'date':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="date" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '" />' . "\n";
                break;
            case 'password':
            case 'number':
            case 'hidden':
                $min = '';
                if (isset($field['min'])) {
                    $min = ' min="' . esc_attr($field['min']) . '"';
                }

                $max = '';
                if (isset($field['max'])) {
                    $max = ' max="' . esc_attr($field['max']) . '"';
                }

                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '"' . $min . $max . '/>' . "\n";
                break;
            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="" />' . "\n";
                break;
            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '">' . $data . '</textarea><br/>' . "\n";
                break;
            case 'checkbox':
                $checked = '';
                if ('on' === $data) {
                    $checked = 'checked="checked"';
                }

                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                break;
            case 'checkbox_multi':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if (in_array($k, (array)$data, true)) {
                        $checked = true;
                    }

                    $html .= '<p><label for="' . esc_attr($field['id'] . '_' . $k) . '" class="checkbox_multi"><input type="checkbox" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '[]" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label></p> ';
                }

                break;
            case 'radio':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if ($k === $data) {
                        $checked = true;
                    }

                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }

                break;
            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if ((string)$k === (string)$data) {
                        $selected = true;
                    }

                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }

                $html .= '</select> ';
                break;
            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) . '" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if (in_array($k, (array)$data, true)) {
                        $selected = true;
                    }

                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }

                $html .= '</select> ';
                break;
            case 'image':
                $image_thumb = '';
                if ($data) {
                    $image_thumb = wp_get_attachment_thumb_url($data);
                }

                $html .= '<img id="' . $option_name . '_preview" alt="' . $option_name . '" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image', Sage::TOKEN) . '" data-uploader_button_text="' . __('Use image', Sage::TOKEN) . '" class="image_upload_button button" value="' . __('Upload new image', Sage::TOKEN) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __('Remove image', Sage::TOKEN) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
                break;
            case 'color':
                ?>
                <div class="color-picker" style="position:relative;">
                    <label>
                        <input type="text" name="<?php esc_attr_e($option_name); ?>" class="color"
                               value="<?php esc_attr_e($data); ?>"/>
                    </label>
                    <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;"
                         class="colorpicker"></div>
                </div>
                <?php
                break;
            case 'editor':
                wp_editor(
                    $data,
                    $option_name,
                    ['textarea_name' => $option_name]
                );
                break;
            case '2_select_multi':
                $html .= TwigService::getInstance()->render('common/form/2_select_multi.html.twig', [
                    'data' => [
                        'optionName' => $option_name,
                        'field' => $field,
                        'values' => $data,
                    ]
                ]);
                break;
            case 'resource':
                $sageService = SageService::getInstance();
                $checked = '';
                if (!empty($data)) {
                    $checked = 'checked="checked"';
                }
                [
                    $data2,
                    $showFields,
                    $filterFields,
                    $hideFields,
                    $perPage,
                    $queryParams,
                ] = GraphqlService::getInstance()->getResourceWithQuery($resource, getData: false, allFilterField: true, withMetadata: false);
                $html .= '
                <div data-checkbox-resource="' . htmlspecialchars(json_encode([
                        'importCondition' => array_map(function (ImportConditionDto $importCondition) {
                            return [
                                'field' => $importCondition->getField(),
                                'value' => $importCondition->getValue(),
                                'condition' => $importCondition->getCondition(),
                            ];
                        }, $resource->getImportCondition()),
                        'allFilterType' => $sageService->getAllFilterType(),
                        'filterFields' => $filterFields,
                    ]), ENT_QUOTES, 'UTF-8') . '">
                    <input type="text" class="hidden" id="' . esc_attr($field['id']) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr($option) . '" />
                    <input id="' . esc_attr($field['id']) . '_select" type="checkbox" ' . $checked . '/>
                    <label for="' . esc_attr($field['id']) . '"><span class="description">' . $field['description'] . '</span></label>
                    <div data-react-resource></div>
                </div>
                ' . "\n";
                break;
        }

        switch ($field['type']) {
            case 'resource':
                break;
            case 'checkbox_multi':
            case 'radio':
            case 'select_multi':
            case '2_select_multi':
                $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;
            default:
                if ($post === null) {
                    $html .= '<label for="' . esc_attr($field['id']) . '">' . "\n";
                }

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                if ($post === null) {
                    $html .= '</label>' . "\n";
                }

                break;
        }

        if (!$echo) {
            return $html;
        }

        echo $html;
        return '';
    }

    public static function registerMenu(): void
    {
        $resources = SageService::getInstance()->getResources();
        $args = apply_filters(
            Sage::TOKEN . '_menu_settings',
            [
                [
                    'location' => 'menu',
                    // Possible settings: options, menu, submenu.
                    'page_title' => __('Sage', Sage::TOKEN),
                    'menu_title' => __('Sage', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_settings',
                    'function' => null,
                    'icon_url' => 'dashicons-rest-api',
                    'position' => 55.5,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __('Settings', Sage::TOKEN),
                    'menu_title' => __('Settings', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_settings',
                    'function' => function (): void {
                        // todo use twig
                        // Build page HTML.
                        $html = TwigService::getInstance()->render('base.html.twig');
                        $html .= '<div class="wrap" id="' . Sage::TOKEN . '_settings">' . "\n";
                        $html .= '<h2>' . __('Sage', Sage::TOKEN) . '</h2>' . "\n";

                        $tab = '';
                        if (isset($_GET['tab']) && $_GET['tab']) {
                            $tab .= $_GET['tab'];
                        }

                        $settings = self::getSettings();
                        // Show page tabs.
                        if (1 < count($settings)) {

                            $html .= '<h2 class="nav-tab-wrapper">' . "\n";

                            $c = 0;
                            foreach ($settings as $section => $data) {

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

                        $html .= '<form method="post" id="form_settings_' . Sage::TOKEN . '" action="options.php" enctype="multipart/form-data">';

                        // Get settings fields.
                        ob_start();
                        settings_fields(Sage::TOKEN . '_settings');
                        do_settings_sections(Sage::TOKEN . '_settings');
                        $html .= ob_get_clean();

                        $html .= '<p class="submit">' . "\n";
                        $html .= '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />' . "\n";
                        $html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr(__('Save Settings', Sage::TOKEN)) . '" />' . "\n";
                        $html .= '</p>' . "\n";
                        $html .= '</form>' . "\n";
                        $html .= '</div>' . "\n";

                        echo $html;
                    },
                    'position' => null,
                ],
                ...array_map(static fn(Resource $resource): array => [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __($resource->getTitle(), Sage::TOKEN),
                    'menu_title' => __($resource->getTitle(), Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_' . $resource->getEntityName(),
                    'function' => static function () use ($resource): void {
                        [
                            $data,
                            $showFields,
                            $filterFields,
                            $hideFields,
                            $perPage,
                            $queryParams,
                        ] = GraphqlService::getInstance()->getResourceWithQuery($resource, getData: false);
                        echo TwigService::getInstance()->render('sage/list.html.twig', [
                            'showFields' => $showFields,
                            'filterFields' => $filterFields,
                            'perPage' => $perPage,
                            'hideFields' => $hideFields,
                            'mandatoryFields' => $resource->getMandatoryFields(),
                            'sageEntityName' => $resource->getEntityName(),
                        ]);
                    },
                    'position' => null,
                ], $resources),
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __('À propos', Sage::TOKEN),
                    'menu_title' => __('À propos', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_about',
                    'function' => static function (): void {
                        echo 'about page';
                    },
                    'position' => null,
                ],
                [
                    'location' => 'submenu',
                    // Possible settings: options, menu, submenu.
                    'parent_slug' => Sage::TOKEN . '_settings',
                    'page_title' => __('Logs', Sage::TOKEN),
                    'menu_title' => __('Logs', Sage::TOKEN),
                    'capability' => 'manage_options',
                    'menu_slug' => Sage::TOKEN . '_log',
                    'function' => static function (): void {
                        echo 'logs page';
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
            }
        }
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

    public static function adminNotices($message): void
    {
        add_action('admin_notices', static function () use ($message): void {
            echo $message;
        });
    }

    public static function getWrongOptions(): string|null
    {
        $pDossier = GraphqlService::getInstance()->getPDossier();
        $sageExpectedOptions = [
            new SageExpectedOption(
                optionName: 'woocommerce_enable_guest_checkout',
                optionValue: 'no',
                trans: __('Allow customers to place orders without an account', 'woocommerce'),
                description: __("Lorsque cette option est activée vos clients ne sont pas obligés de se connecter à leurs comptes pour passer commande et il est donc impossible de créer automatiquement la commande passé dans Woocommerce dans Sage.", Sage::TOKEN),
            ),
            new SageExpectedOption(
                optionName: 'woocommerce_calc_taxes',
                optionValue: 'yes',
                trans: __('Enable tax rates and calculations', 'woocommerce'),
                description: __("Cette option doit être activé pour que le plugin Sage fonctionne correctement afin de récupérer les taxes directement renseignées dans Sage.", Sage::TOKEN),
            ),
        ];
        if (!is_null($pDossier?->nDeviseCompteNavigation?->dCodeIso)) {
            $sageExpectedOptions[] = new SageExpectedOption(
                optionName: 'woocommerce_currency',
                optionValue: $pDossier->nDeviseCompteNavigation->dCodeIso,
                trans: __('Currency', 'woocommerce'),
                description: __("La devise dans Woocommerce n'est pas la même que dans Sage.", Sage::TOKEN),
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
            $result = "<div class='error''>";
            $fieldsForm = '';
            $optionNames = [];
            foreach ($changes as $sageExpectedOption) {
                // todo use twig
                $optionValue = $sageExpectedOption->getOptionValue();
                $result .= "<div>" . __('Le plugin Sage a besoin de modifier l\'option', Sage::TOKEN) . " <code>" .
                    $sageExpectedOption->getTrans() . "</code> " . __('pour lui donner la valeur', Sage::TOKEN) . " <code>" .
                    $optionValue . "</code>
<div class='tooltip'>
        <span class='dashicons dashicons-info' style='padding-right: 22px'></span>
        <div class='tooltiptext' style='right: 0'>" . $sageExpectedOption->getDescription() . "</div>
    </div>
</div>";
                $optionName = $sageExpectedOption->getOptionName();
                $fieldsForm .= '<input type="hidden" name="' . $optionName . '" value="' . $optionValue . '">';
                $optionNames[] = $optionName;
            }
            $result .= '<form method="post" action="options.php" enctype="multipart/form-data">'
                . $fieldsForm
                . '<input type="hidden" name="page_options" value="' . esc_attr(implode(',', $optionNames)) . '"/>
                <input type="hidden" name="_wp_http_referer" value="' . esc_attr($_SERVER["REQUEST_URI"]) . '">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="option_page" value="options"/>'
                . wp_nonce_field('options-options', '_wpnonce', true, false)
                . '<p class="submit">
                <input name="Update" type="submit" class="button-primary" value="' . esc_attr(__('Mettre à jour', Sage::TOKEN)) . '">
                </p>
                </form>
                </div>';
            return $result;
        }
        return null;
    }
}
