<?php

namespace App;

use App\class\Dto\ArgumentSelectionSetDto;
use App\class\SageEntityMenu;
use App\class\SageEntityMetadata;
use App\class\SageShippingMethod__index__;
use App\lib\SageRequest;
use App\Utils\PathUtils;
use App\Utils\SageTranslationUtils;
use App\Utils\TaxeUtils;
use Automattic\WooCommerce\Utilities\OrderUtil;
use DateTime;
use PHPHtmlParser\Dom;
use stdClass;
use Swaggest\JsonDiff\JsonDiff;
use WC_Meta_Box_Order_Data;
use WC_Order;
use WC_Product;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Tax;
use WP_Application_Passwords;
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
    public final const TARGET_PANEL = Sage::TOKEN . '_product_data';
    public const META_DATA_PREFIX = self::PREFIX_META_DATA . '_' . Sage::TOKEN;
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
    public readonly array $sageEntityMenus;

    /**
     * Constructor function.
     *
     * @param Sage|null $sage Parent object.
     */
    private function __construct(public ?Sage $sage)
    {
        $sageSettings = $this;
        $sageWoocommerce = $this->sage->sageWoocommerce;
        $sageGraphQl = $this->sage->sageGraphQl;
        global $wpdb;
        $this->sageEntityMenus = [
            new SageEntityMenu(
                title: __("Clients", Sage::TOKEN),
                // todo afficher les clients Sage qui partagent le même email et expliqués qu'il ne seront pas dupliqués sur le site
                description: __("Gestion des clients.", Sage::TOKEN),
                entityName: SageEntityMenu::FCOMPTET_ENTITY_NAME,
                typeModel: SageEntityMenu::FCOMPTET_TYPE_MODEL,
                defaultSortField: SageEntityMenu::FCOMPTET_DEFAULT_SORT,
                defaultFields: [
                    'ctNum',
                    'ctIntitule',
                    'ctContact',
                    'ctEmail',
                    self::META_DATA_PREFIX . '_last_update',
                    self::META_DATA_PREFIX . '_postId',
                ],
                mandatoryFields: [
                    'ctNum',
                    'ctType', // to show import in sage button or not
                ],
                filterType: SageEntityMenu::FCOMPTET_FILTER_TYPE,
                transDomain: SageTranslationUtils::TRANS_FCOMPTETS,
                options: [
                    [
                        'id' => 'auto_create_' . Sage::TOKEN . '_fcomptet',
                        'label' => __('Créer automatiquement le client Sage', Sage::TOKEN),
                        'description' => __("Créer automatiquement un compte client dans Sage lorsqu'un compte Wordpress est crée.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                    [
                        'id' => 'auto_import_' . Sage::TOKEN . '_fcomptet',
                        'label' => __('Importer automatiquement les anciens clients Woocommerce', Sage::TOKEN),
                        'description' => __("Importe les comptes Woocommerce dans Sage à compter de la date renseignée (date de création du compte dans Woocommerce). Laissez vide pour ne pas importer.", Sage::TOKEN),
                        'type' => 'date',
                        'default' => '',
                        'placeholder' => __('', Sage::TOKEN)
                    ],
                    [
                        'id' => 'auto_create_wordpress_account',
                        'label' => __('Créer automatiquement le compte Wordpress', Sage::TOKEN),
                        'description' => __("Créer automatiquement un compte dans Wordpress lorsqu'un utilisateur Sage est crée.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                    [
                        'id' => 'auto_import_wordpress_account',
                        'label' => __('Importer automatiquement les anciens clients Sage', Sage::TOKEN),
                        'description' => __("Importe les comptes Sage dans Woocommerce à compter de la date renseignée (date de création du compte dans Sage). Laissez vide pour ne pas importer.", Sage::TOKEN),
                        'type' => 'date',
                        'default' => '',
                        'placeholder' => __('', Sage::TOKEN)
                    ],
                    [
                        'id' => 'mail_auto_create_' . Sage::TOKEN . '_fcomptet',
                        'label' => __('Envoyer automatiquement le mail pour définir le mot de passe', Sage::TOKEN),
                        'description' => __("Lorsqu'un compte Wordpress est créé à partir d'un compte Sage, un mail pour définir le mot de passe du compte Wordpress est automatiquement envoyé à l'utilisateur.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                    [
                        'id' => 'auto_update_' . Sage::TOKEN . '_fcomptet_when_edit_account',
                        'label' => __("Mettre à jour automatiquement un compte Sage lorsqu'un compte Wordpress est modifié", Sage::TOKEN),
                        'description' => __("Lorsque qu’un utilisateur WordPress met à jour ses informations, ou lorsqu’un administrateur modifie les informations d’un compte WordPress, celles-ci sont également mises à jour dans Sage si un compte y est lié.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                    [
                        'id' => 'auto_update_account_when_edit_' . Sage::TOKEN . '_fcomptet',
                        'label' => __("Mettre à jour automatiquement un compte Wordpress lorsqu'un compte Sage est modifié", Sage::TOKEN),
                        'description' => __("Lorsque les informations d’un compte Sage sont modifiées, elles sont également mises à jour dans WordPress si un compte y est lié.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                ],
                metadata: static function (?stdClass $obj = null) use ($sageGraphQl, $sageSettings): array {
                    $result = [
                        new SageEntityMetadata(field: '_last_update', value: static function (StdClass $fComptet) {
                            return (new DateTime())->format('Y-m-d H:i:s');
                        }, showInOptions: true),
                        new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
                    ];
                    return $sageSettings->addSelectionSetAsMetadata($sageGraphQl->_getFComptetSelectionSet(), $result, $obj);
                },
                metaKeyIdentifier: Sage::META_KEY_CT_NUM,
                metaTable: $wpdb->usermeta,
                metaColumnIdentifier: 'user_id',
                canImport: static function (array $fComptet) use ($sage) {
                    return $sage->canUpdateUserOrFComptet((object)$fComptet);
                },
                import: static function (string $identifier) use ($sageSettings) {
                    [$userId, $message] = $sageSettings->sage->updateUserOrFComptet($identifier, ignorePingApi: true);
                    return $userId;
                },
                selectionSet: $sageGraphQl->_getFComptetSelectionSet(),
            ),
            new SageEntityMenu(
                title: __("Documents", Sage::TOKEN),
                description: __("Gestion Commerciale / Menu Traitement / Documents des ventes, des achats, des stocks et internes / Fenêtre Document", Sage::TOKEN),
                entityName: SageEntityMenu::FDOCENTETE_ENTITY_NAME,
                typeModel: SageEntityMenu::FDOCENTETE_TYPE_MODEL,
                defaultSortField: SageEntityMenu::FDOCENTETE_DEFAULT_SORT,
                defaultFields: [
                    'doDomaine',
                    'doPiece',
                    'doType',
                    'doDate',
                    self::META_DATA_PREFIX . '_last_update',
                    self::META_DATA_PREFIX . '_postId',
                ],
                mandatoryFields: [
                    'doDomaine', // to show import in sage button or not
                    'doPiece',
                    'doType',
                ],
                filterType: SageEntityMenu::FDOCENTETE_FILTER_TYPE,
                transDomain: SageTranslationUtils::TRANS_FDOCENTETES,
                options: [
                    [
                        'id' => 'auto_create_' . Sage::TOKEN . '_fdocentete',
                        'label' => __('Créer automatiquement le document de vente Sage', Sage::TOKEN),
                        'description' => __("Créer automatiquement un document de vente dans Sage lorsqu'une commande Woocommerce est crée.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                    [
                        'id' => 'auto_create_wordpress_order',
                        'label' => __('Créer automatiquement la commande Woocommerce', Sage::TOKEN),
                        'description' => __("Créer automatiquement une commande dans Woocommerce lorsqu'un document de vente Sage est crée pour les types de documents sélectionnés.", Sage::TOKEN),
                        'type' => '2_select_multi',
                        'options' => [
                            '0' => __("Devis", Sage::TOKEN),
                            '1' => __("Bon de commande", Sage::TOKEN),
                            '2' => __("Préparation de livraison", Sage::TOKEN),
                            '3' => __("Bon de livraison", Sage::TOKEN),
                            '6' => __("Facture", Sage::TOKEN),
                            '7' => __("Facture comptabilisée", Sage::TOKEN),
                        ],
                        'default' => [],
                        'sort' => false,
                    ],
                    [
                        'id' => 'auto_import_wordpress_order_date',
                        'label' => __('Importer automatiquement les anciens documents de vente Sage', Sage::TOKEN),
                        'description' => __("Importe les documents de vente Sage dans Woocommerce à compter de la date renseignée (date de création du compte dans Sage). Laissez vide pour ne pas importer.", Sage::TOKEN),
                        'type' => 'date',
                        'default' => '',
                        'placeholder' => __('', Sage::TOKEN)
                    ],
                    [
                        'id' => 'auto_import_wordpress_order_dotype',
                        'label' => '',
                        'description' => __("Importe les documents de vente Sage dans Woocommerce qui ont les status sélectionnés. Laissez vide pour ne pas importer.", Sage::TOKEN),
                        'type' => '2_select_multi',
                        'options' => [
                            '0' => __("Devis", Sage::TOKEN),
                            '1' => __("Bon de commande", Sage::TOKEN),
                            '2' => __("Préparation de livraison", Sage::TOKEN),
                            '3' => __("Bon de livraison", Sage::TOKEN),
                            '6' => __("Facture", Sage::TOKEN),
                            '7' => __("Facture comptabilisée", Sage::TOKEN),
                        ],
                        'default' => [],
                        'sort' => false,
                    ],
                ],
                metadata: static function (?stdClass $obj = null) use ($sageGraphQl, $sageSettings): array {
                    $result = [
                        new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
                    ];
                    return $sageSettings->addSelectionSetAsMetadata($sageGraphQl->_getFDocenteteSelectionSet(), $result, $obj);
                },
                metaKeyIdentifier: Sage::META_KEY_IDENTIFIER,
                metaTable: $wpdb->prefix . 'wc_orders_meta',
                metaColumnIdentifier: 'order_id',
                canImport: static function (array $fDocentete) use ($sageWoocommerce) {
                    return $sageWoocommerce->canImportOrderFromSage((object)$fDocentete);
                },
                import: static function (string $identifier) use ($sageWoocommerce) {
                    $data = json_decode(stripslashes($identifier), false, 512, JSON_THROW_ON_ERROR);
                    [$message, $order] = $sageWoocommerce->importFDocenteteFromSage($data->doPiece, $data->doType);
                    return $order->get_id();
                },
                selectionSet: $sageGraphQl->_getFDocenteteSelectionSet(),
                getIdentifier: static function (array $fDocentete) {
                    return json_encode(['doPiece' => $fDocentete["doPiece"], 'doType' => $fDocentete["doType"]], JSON_THROW_ON_ERROR);
                },
            ),
            new SageEntityMenu(
                title: __("Articles", Sage::TOKEN),
                description: __("Gestion des articles", Sage::TOKEN),
                entityName: SageEntityMenu::FARTICLE_ENTITY_NAME,
                typeModel: SageEntityMenu::FARTICLE_TYPE_MODEL,
                defaultSortField: SageEntityMenu::FARTICLE_DEFAULT_SORT,
                defaultFields: [
                    'arRef',
                    'arDesign',
                    'arType',
                    'arPublie',
                    self::META_DATA_PREFIX . '_last_update',
                    self::META_DATA_PREFIX . '_postId',
                ],
                mandatoryFields: [
                    'arRef',
                    'arType', // to show import in sage button or not
                    'arNomencl', // to show import in sage button or not
                    'arPublie', // to show import in sage button or not
                ],
                filterType: SageEntityMenu::FARTICLE_FILTER_TYPE,
                transDomain: SageTranslationUtils::TRANS_FARTICLES,
                options: [
                    [
                        'id' => 'auto_create_wordpress_article',
                        'label' => __('Créer automatiquement le produit Woocommerce', Sage::TOKEN),
                        'description' => __("Créer automatiquement le produit dans Woocommerce lorsqu'un article Sage est crée.", Sage::TOKEN),
                        'type' => 'checkbox',
                        'default' => 'off'
                    ],
                    [
                        'id' => 'auto_import_wordpress_article',
                        'label' => __('Importer automatiquement les anciens produits Sage', Sage::TOKEN),
                        'description' => __("Importe les produits Sage dans Woocommerce à compter de la date renseignée (date de création de l'article dans Sage). Laissez vide pour ne pas importer.", Sage::TOKEN),
                        'type' => 'date',
                        'default' => '',
                        'placeholder' => __('', Sage::TOKEN)
                    ],
                    // todo ajouter une option pour considérer les catalogues comme des catégories
                ],
                metadata: static function (?stdClass $obj = null) use ($sageWoocommerce, $sageGraphQl, $sageSettings): array {
                    $result = [
                        new SageEntityMetadata(field: '_prices', value: static function (StdClass $fArticle) {
                            return json_encode($fArticle->prices, JSON_THROW_ON_ERROR);
                        }),
                        new SageEntityMetadata(field: '_max_price', value: static function (StdClass $fArticle) use ($sageWoocommerce) {
                            return json_encode($sageWoocommerce->getMaxPrice($fArticle->prices), JSON_THROW_ON_ERROR);
                        }),
                        new SageEntityMetadata(field: '_last_update', value: static function (StdClass $fArticle) {
                            return (new DateTime())->format('Y-m-d H:i:s');
                        }, showInOptions: true),
                        new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
                        new SageEntityMetadata(field: '_canEditArSuiviStock', value: static function (StdClass $fArticle) {
                            return $fArticle->canEditArSuiviStock;
                        }),
                    ];
                    return $sageSettings->addSelectionSetAsMetadata($sageGraphQl->_getFArticleSelectionSet(), $result, $obj);
                },
                metaKeyIdentifier: Sage::META_KEY_AR_REF,
                metaTable: $wpdb->postmeta,
                metaColumnIdentifier: 'post_id',
                canImport: static function (array $fArticle) use ($sageWoocommerce) {
                    return $sageWoocommerce->canImportFArticle((object)$fArticle);
                },
                import: static function (string $identifier) use ($sageWoocommerce) {
                    [$response, $responseError, $message, $postId] = $sageWoocommerce->importFArticleFromSage(
                        $identifier,
                        ignorePingApi: true,
                    );
                    return $postId;
                },
                selectionSet: $sageGraphQl->_getFArticleSelectionSet(),
            ),
        ];
        // Initialise settings.
        add_action('init', function (): void {
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
                        /** region available options types
                         * [
                         * 'id' => 'text_field',
                         * 'label' => __('Some Text', Sage::TOKEN),
                         * 'description' => __('This is a standard text field.', Sage::TOKEN),
                         * 'type' => 'text',
                         * 'default' => '',
                         * 'placeholder' => __('Placeholder text', Sage::TOKEN)
                         * ],
                         * [
                         * 'id' => 'password_field',
                         * 'label' => __('A Password', Sage::TOKEN),
                         * 'description' => __('This is a standard password field.', Sage::TOKEN),
                         * 'type' => 'password',
                         * 'default' => '',
                         * 'placeholder' => __('Placeholder text', Sage::TOKEN)
                         * ],
                         * [
                         * 'id' => 'secret_text_field',
                         * 'label' => __('Some Secret Text', Sage::TOKEN),
                         * 'description' => __('This is a secret text field - any data saved here will not be displayed after the page has reloaded, but it will be saved.', Sage::TOKEN),
                         * 'type' => 'text_secret',
                         * 'default' => '',
                         * 'placeholder' => __('Placeholder text', Sage::TOKEN)
                         * ],
                         * [
                         * 'id' => 'text_block',
                         * 'label' => __('A Text Block', Sage::TOKEN),
                         * 'description' => __('This is a standard text area.', Sage::TOKEN),
                         * 'type' => 'textarea',
                         * 'default' => '',
                         * 'placeholder' => __('Placeholder text for this textarea', Sage::TOKEN)
                         * ],
                         * [
                         * 'id' => 'single_checkbox',
                         * 'label' => __('An Option', Sage::TOKEN),
                         * 'description' => __("A standard checkbox - if you save this option as checked then it will store the option as 'on', otherwise it will be an empty string.", Sage::TOKEN),
                         * 'type' => 'checkbox',
                         * 'default' => ''
                         * ],
                         * [
                         * 'id' => 'select_box',
                         * 'label' => __('A Select Box', Sage::TOKEN),
                         * 'description' => __('A standard select box.', Sage::TOKEN),
                         * 'type' => 'select',
                         * 'options' => ['drupal' => 'Drupal', 'joomla' => 'Joomla', 'wordpress' => 'WordPress'],
                         * 'default' => 'wordpress'
                         * ],
                         * [
                         * 'id' => 'radio_buttons',
                         * 'label' => __('Some Options', Sage::TOKEN),
                         * 'description' => __('A standard set of radio buttons.', Sage::TOKEN),
                         * 'type' => 'radio',
                         * 'options' => ['superman' => 'Superman', 'batman' => 'Batman', 'ironman' => 'Iron Man'],
                         * 'default' => 'batman'
                         * ],
                         * [
                         * 'id' => 'multiple_checkboxes',
                         * 'label' => __('Some Items', Sage::TOKEN),
                         * 'description' => __('You can select multiple items and they will be stored as an array.', Sage::TOKEN),
                         * 'type' => 'checkbox_multi',
                         * 'options' =>
                         * ['square' => 'Square', 'circle' => 'Circle', 'rectangle' => 'Rectangle', 'triangle' => 'Triangle'],
                         * 'default' => ['circle', 'triangle']
                         * ],
                         * [
                         * 'id' => 'number_field',
                         * 'label' => __('A Number', Sage::TOKEN),
                         * 'description' => __('This is a standard number field - if this field contains anything other than numbers then the form will not be submitted.', Sage::TOKEN),
                         * 'type' => 'number',
                         * 'default' => '',
                         * 'placeholder' => __('42', Sage::TOKEN)
                         * ],
                         * [
                         * 'id' => 'colour_picker',
                         * 'label' => __('Pick a colour', Sage::TOKEN),
                         * 'description' => __("This uses WordPress' built-in colour picker - the option is stored as the colour's hex code.", Sage::TOKEN),
                         * 'type' => 'color',
                         * 'default' => '#21759B'
                         * ],
                         * [
                         * 'id' => 'an_image',
                         * 'label' => __('An Image', Sage::TOKEN),
                         * 'description' => __('This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an imge the thumbnail will display above these buttons.', Sage::TOKEN),
                         * 'type' => 'image',
                         * 'default' => '',
                         * 'placeholder' => ''
                         * ],
                         * [
                         * 'id' => 'multi_select_box',
                         * 'label' => __('A Multi-Select Box', Sage::TOKEN),
                         * 'description' => __('A standard multi-select box - the saved data is stored as an array.', Sage::TOKEN),
                         * 'type' => 'select_multi',
                         * 'options' => ['linux' => 'Linux', 'mac' => 'Mac', 'windows' => 'Windows'],
                         * 'default' => ['linux']
                         * ],
                         **/
                    ]
                ],
            ];
            foreach ($this->sageEntityMenus as $sageEntityMenu) {
                $fieldOptions = $this->getFieldsForEntity($sageEntityMenu);
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
                        'options' => array_combine(self::$paginationRange, self::$paginationRange),
                        'default' => (string)self::$defaultPagination
                    ],
                    [
                        'id' => $sageEntityMenu->getEntityName() . '_filter_fields',
                        'label' => __('Champs pouvant être filtrés', Sage::TOKEN),
                        'description' => __('Veuillez sélectionner les champs pouvant servir à filter vos résultats.', Sage::TOKEN),
                        'type' => '2_select_multi',
                        'options' => array_filter($fieldOptions, static function (string $key) {
                            return !str_starts_with($key, SageSettings::PREFIX_META_DATA);
                        }, ARRAY_FILTER_USE_KEY),
                        'default' => array_filter($defaultFields, static function (string $v) {
                            return !str_starts_with($v, SageSettings::PREFIX_META_DATA);
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
                        'page_title' => __('Sage', Sage::TOKEN),
                        'menu_title' => __('Sage', Sage::TOKEN),
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
                        'page_title' => __('Settings', Sage::TOKEN),
                        'menu_title' => __('Settings', Sage::TOKEN),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::TOKEN . '_settings',
                        'function' => function (): void {
                            // todo use twig
                            // Build page HTML.
                            $html = $this->sage->twig->render('base.html.twig');
                            $html .= '<div class="wrap" id="' . Sage::TOKEN . '_settings">' . "\n";
                            $html .= '<h2>' . __('Sage', Sage::TOKEN) . '</h2>' . "\n";

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
                    ...array_map(static fn(SageEntityMenu $sageEntityMenu): array => [
                        'location' => 'submenu',
                        // Possible settings: options, menu, submenu.
                        'parent_slug' => Sage::TOKEN . '_settings',
                        'page_title' => __($sageEntityMenu->getTitle(), Sage::TOKEN),
                        'menu_title' => __($sageEntityMenu->getTitle(), Sage::TOKEN),
                        'capability' => self::$capability,
                        'menu_slug' => Sage::TOKEN . '_' . $sageEntityMenu->getEntityName(),
                        'function' => static function () use ($sageSettings, $sageEntityMenu): void {
                            [
                                $data,
                                $showFields,
                                $filterFields,
                                $hideFields,
                                $perPage,
                                $queryParams,
                            ] = $sageSettings->sage->sageGraphQl->getSageEntityMenuWithQuery($sageEntityMenu, getData: false);
                            echo $sageSettings->sage->twig->render('sage/list.html.twig', [
                                'showFields' => $showFields,
                                'filterFields' => $filterFields,
                                'perPage' => $perPage,
                                'hideFields' => $hideFields,
                                'mandatoryFields' => $sageEntityMenu->getMandatoryFields(),
                                'sageEntityName' => $sageEntityMenu->getEntityName(),
                            ]);
                        },
                        'position' => null,
                    ],
                        $this->sageEntityMenus),
                    [
                        'location' => 'submenu',
                        // Possible settings: options, menu, submenu.
                        'parent_slug' => Sage::TOKEN . '_settings',
                        'page_title' => __('À propos', Sage::TOKEN),
                        'menu_title' => __('À propos', Sage::TOKEN),
                        'capability' => self::$capability,
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
                        'capability' => self::$capability,
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

//                    add_action('admin_print_styles-' . $page, function (): void {
//                        // We're including the farbtastic script & styles here because they're needed for the colour picker
//                        // If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below.
//                        wp_enqueue_style('farbtastic');
//                        wp_enqueue_script('farbtastic');
//
//                        // We're including the WP media scripts here because they're needed for the image upload field.
//                        // If you're not including an image upload then you can leave this function call out.
//                        wp_enqueue_media();
//
//                        wp_register_script(Sage::TOKEN . '-settings-js', $this->sage->assets_url . 'js/settings' . $this->sage->script_suffix . '.js', ['farbtastic', 'jquery'], '1.0.0', true);
//                        wp_enqueue_script(Sage::TOKEN . '-settings-js');
//                    });
                }
            }
        });

        // Add settings link to plugins page.
        add_filter(
            'plugin_action_links_' . plugin_basename($this->sage->file),
            static function (array $links): array {
                $links[] = '<a href="options-general.php?page=' . Sage::TOKEN . '_settings">' . __('Settings', Sage::TOKEN) . '</a>';
                return $links;
            }
        );

        // Configure placement of plugin settings page. See readme for implementation.
        add_filter(Sage::TOKEN . '_menu_settings', static fn(array $settings = []): array => $settings);

        // region WooCommerce
        add_filter('product_type_selector', static function (array $types): array {
            $arRef = Sage::getArRef(get_the_ID());
            if (!empty($arRef)) {
                return [Sage::TOKEN => __('Sage', Sage::TOKEN)];
            }
            return array_merge([Sage::TOKEN => __('Sage', Sage::TOKEN)], $types);
        });

        add_action('add_meta_boxes', static function (string $screen, mixed $obj) use ($sageSettings): void { // remove [Product type | virtual | downloadable] add product arRef
            if ($screen === 'product') {
                global $wp_meta_boxes;
                $sageSettings->showMetaBoxProduct($wp_meta_boxes, $screen);
            } else if ($screen === 'woocommerce_page_wc-orders') {
                global $wp_meta_boxes;
                $sageSettings->showMetaBoxOrder($wp_meta_boxes, $screen);
            }
        }, 40, 2); // woocommerce/includes/admin/class-wc-admin-meta-boxes.php => 40 > 30 : add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );

        // region Custom Product Tabs In WooCommerce https://aovup.com/woocommerce/add-tabs/
        add_filter('woocommerce_product_data_tabs', static function (array $tabs) { // Code to Create Tab in the Backend
            foreach ($tabs as $tabName => $value) {
                if (!in_array($tabName, [
                    'linked_product',
                    'advanced',
                ])) {
                    $tabs[$tabName]["class"][] = 'hide_if_' . Sage::TOKEN;
                }
            }

            $tabs[Sage::TOKEN] = [
                'label' => __('Sage', Sage::TOKEN),
                'target' => self::TARGET_PANEL,
                'class' => ['show_if_' . Sage::TOKEN],
                'priority' => 0,
            ];
            return $tabs;
        });

        add_action('woocommerce_product_data_panels', static function () use ($sageSettings): void { // Code to Add Data Panel to the Tab
            $product = wc_get_product();
            if (!($product instanceof WC_Product)) {
                return;
            }
            $oldMetaData = $product->get_meta_data();
            $arRef = $product->get_meta(Sage::META_KEY_AR_REF);
            $meta = [
                'changes' => [],
                'old' => $oldMetaData,
                'new' => $oldMetaData,
            ];
            $responseError = null;
            $updateApi = $product->get_meta('_' . Sage::TOKEN . '_updateApi'); // returns "" if not exists in bdd
            $fArticle = $sageSettings->sage->sageGraphQl->getFArticle($arRef);
            if (!empty($arRef) && empty($updateApi)) {
                [$response, $responseError, $message, $postId] = $sageSettings->sage->sageWoocommerce->importFArticleFromSage($arRef, ignoreCanImport: true, fArticle: $fArticle);
                if (is_null($responseError)) {
                    $product->read_meta_data(true);
                    $meta['new'] = $product->get_meta_data();
                    foreach ($meta as $key => $value) {
                        $meta[$key . 'Array'] = new stdClass();
                        foreach ($value as $metaItem) {
                            $data = $metaItem->get_data();
                            if ($data['key'] === '_' . Sage::TOKEN . '_last_update') {
                                continue;
                            }
                            $meta[$key . 'Array']->{$data['key']} = $data['value'];
                        }
                    }
                    $jsonDiff = new JsonDiff($meta['oldArray'], $meta['newArray']);
                    $meta['changes'] = [
                        'removed' => (array)$jsonDiff->getRemoved(),
                        'added' => (array)$jsonDiff->getAdded(),
                        'modified' => (array)$jsonDiff->getModifiedNew(),
                    ];
                }
            }
            $changeTypes = ['added', 'removed', 'modified'];
            $hasChanges = false;
            foreach ($changeTypes as $type) {
                if (!empty($meta['changes'][$type])) {
                    $hasChanges = true;
                    break;
                }
            }
            if ($hasChanges) {
                foreach ($changeTypes as $type) {
                    foreach ($meta['changes'][$type] as $key => $value) {
                        if (is_array($value) || is_object($value)) {
                            $meta['changes'][$type][$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                        }
                    }
                }
            }
            $meta["changes"]["removed"] = array_filter($meta["changes"]["removed"], static function (string $value) {
                return !empty($value);
            });
            echo $sageSettings->sage->twig->render('woocommerce/tabs/sage.html.twig', [
                'fArticle' => $fArticle,
                'pCattarifs' => $sageSettings->sage->sageGraphQl->getPCattarifs(),
                'fCatalogues' => $sageSettings->sage->sageGraphQl->getFCatalogues(),
                'pCatComptas' => $sageSettings->sage->sageGraphQl->getPCatComptas(),
                'fFamilles' => $sageSettings->sage->sageGraphQl->getFFamilles(),
                'pUnites' => $sageSettings->sage->sageGraphQl->getPUnites(),
                'fDepots' => $sageSettings->sage->sageGraphQl->getFDepots(),
                'fPays' => $sageSettings->sage->sageGraphQl->getFPays(),
                'pPreference' => $sageSettings->sage->sageGraphQl->getPPreference(),
                'panelId' => self::TARGET_PANEL,
                'responseError' => $responseError,
                'metaChanges' => $meta['changes'],
                'productMeta' => $meta['new'],
                'updateApi' => $updateApi,
                'hasChanges' => $hasChanges,
            ]);
        });
        // endregion

        // region taxes
        // woocommerce/includes/admin/settings/views/html-settings-tax.php
        // woocommerce/includes/admin/views/html-admin-settings.php
        add_action('woocommerce_sections_tax', static function () use ($sageSettings): void {
            $sageSettings->updateTaxes();
            if (array_key_exists('section', $_GET) && $_GET['section'] === Sage::TOKEN) {
                ?>
                <div class="notice notice-info">
                    <p>
                        <?= __("Veuillez ne pas modifier les taxes Sage manuellement ici, elles sont automatiquement mises à jour en fonction des taxes dans Sage ('Stucture' -> 'Comptabilité' -> 'Taux de taxes').", Sage::TOKEN) ?>
                    </p>
                </div>
                <?php
            }
        });
        // endregion

        // region add sage shipping methods
        add_filter('woocommerce_shipping_methods', static function (array $result) use ($sageSettings) {
            $className = pathinfo(str_replace('\\', '/', SageShippingMethod__index__::class), PATHINFO_FILENAME);
            $pExpeditions = $sageSettings->sage->sageGraphQl->getPExpeditions(
                getError: true,
            );
            if (Sage::showErrors($pExpeditions)) {
                return $result;
            }
            if (
                $pExpeditions !== [] &&
                !class_exists(str_replace('__index__', '0', $className))
            ) {
                preg_match(
                    '/class ' . $className . '[\s\S]*/',
                    file_get_contents(__DIR__ . '/class/' . $className . '.php'),
                    $skeletonShippingMethod);
                foreach ($pExpeditions as $i => $pExpedition) {
                    $thisSkeletonShippingMethod = str_replace(
                        ['__index__', '__id__', '__name__', '__description__'],
                        [
                            $i,
                            $pExpedition->slug,
                            '[' . __('Sage', Sage::TOKEN) . '] ' . $pExpedition->eIntitule,
                            '<span style="font-weight: bold">[' . __('Sage', Sage::TOKEN) . ']</span> ' . $pExpedition->eIntitule,
                        ],
                        $skeletonShippingMethod[0]
                    );
                    eval(str_replace('@TOKEN@', Sage::TOKEN, $thisSkeletonShippingMethod));
                }
            }
            foreach ($pExpeditions as $i => $pExpedition) {
                $result[$pExpedition->slug] = str_replace('__index__', $i, $className);
            }
            return $result;
        });
        add_action('woocommerce_settings_shipping', static function () {
            global $wpdb;
            $r = $wpdb->get_results(
                $wpdb->prepare("
SELECT COUNT(instance_id) nbInstance
FROM {$wpdb->prefix}woocommerce_shipping_zone_methods
WHERE method_id NOT LIKE '" . Sage::TOKEN . "%'
  AND is_enabled = 1
"));
            if ((int)$r[0]->nbInstance > 0) {
                echo '
<div class="notice notice-warning"><p>
    <span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">
        ' . __('Certain Mode(s) d’expédition qui ne proviennent pas de Sage sont activés. Cliquez sur "Désactiver" pour désactiver les modes d\'expéditions qui ne proviennent pas de Sage', Sage::TOKEN) . '
    </span>
    <strong>
    <span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;">
        <a href="' . get_site_url() . '/index.php?rest_route=' . urlencode('/' . Sage::TOKEN . '/v1/deactivate-shipping-zones') . '&_wpnonce=' . wp_create_nonce('wp_rest') . '">
        ' . __('Désactiver', Sage::TOKEN) . '
        </a>
    </span>
    </strong>
</p></div>
                ';
            }
        });
        // endregion
        // endregion

        // region user
        // region user save meta with API: https://wordpress.stackexchange.com/a/422521/201039
        $userMetaProp = self::PREFIX_META_DATA;
        add_filter('rest_pre_insert_user', static function ( // /!\ aussi trigger lorsque l'on update un user
            stdClass        $prepared_user,
            WP_REST_Request $request
        ) use ($userMetaProp): stdClass {
            if (!empty($request['meta'])) {
                $prepared_user->{$userMetaProp} = [];
                $ctNum = null;
                foreach ($request['meta'] as $key => $value) {
                    if ($key === Sage::META_KEY_CT_NUM) {
                        $ctNum = $value;
                    }
                    $prepared_user->{$userMetaProp}[$key] = $value;
                }
                if (!is_null($ctNum)) {
                    global $wpdb;
                    $r = $wpdb->get_results(
                        $wpdb->prepare("
SELECT {$wpdb->users}.ID, {$wpdb->users}.user_login
FROM {$wpdb->usermeta}
    INNER JOIN {$wpdb->users} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id
WHERE meta_key = %s
  AND meta_value = %s
", [Sage::META_KEY_CT_NUM, $ctNum]));
                    if (
                        !empty($r) &&
                        (
                            !property_exists($prepared_user, 'ID') ||
                            (int)$r[0]->ID !== $prepared_user->ID
                        )
                    ) {
                        wp_send_json_error([
                            'existing_user_ctNum' => __("Le compte Sage [" . $ctNum . "] est déjà lié au compte Wordpress [" . $r[0]->user_login . " (id: " . $r[0]->ID . ")]"),
                        ]);
                    }
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
        add_action('rest_after_insert_user', static function (
            WP_User         $user,
            WP_REST_Request $request,
            bool            $creating
        ): void {
            if ($creating) {
                $sendMail = (bool)get_option(Sage::TOKEN . '_auto_send_mail_import_' . Sage::TOKEN . '_fcomptet');
                if ($sendMail) {
                    // Accepts only 'user', 'admin' , 'both' or default '' as $notify.
                    wp_send_new_user_notifications($user->ID, 'user');
                }
            }
        }, accepted_args: 3);
        // endregion
        // region user show Sage id: https://wordpress.stackexchange.com/a/160423/201039
        add_filter('manage_users_columns', static function (array $columns): array {
            $columns[Sage::TOKEN] = __("Sage", Sage::TOKEN);
            return $columns;
        });
        add_filter('manage_users_custom_column', static function (string $val, string $columnName, int $userId) use ($sageSettings): string {
            return $sageSettings->sage->getUserWordpressIdForSage($userId) ?? '';
        }, accepted_args: 3);
        // endregion
        // endregion
    }

    private function addSelectionSetAsMetadata(array $selectionSets, array &$sageEntityMetadatas, ?stdClass $obj, string $prefix = ''): array
    {
        foreach ($selectionSets as $subEntity => $selectionSet) {
            if (is_array($selectionSet) && array_key_exists('name', $selectionSet)) {
                $sageEntityMetadatas[] = new SageEntityMetadata(field: '_' . $prefix . $selectionSet['name'], value: static function (StdClass $entity) use ($selectionSet, $prefix) {
                    return PathUtils::getByPath($entity, $prefix)->{$selectionSet['name']};
                });
            } else if (!is_null($obj) && $selectionSet instanceof ArgumentSelectionSetDto) {
                foreach ($obj->{$subEntity} as $subObject) {
                    $this->addSelectionSetAsMetadata(
                        $selectionSet->getSelectionSet(),
                        $sageEntityMetadatas,
                        $subObject,
                        $subEntity . '[' . $subObject->{$selectionSet->getKey()} . '].'
                    );
                }
            }
        }
        return $sageEntityMetadatas;
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
        $prefix = self::META_DATA_PREFIX;
        foreach ($sageEntityMenu->getMetadata() as $metadata) {
            if (!$metadata->getShowInOptions()) {
                continue;
            }
            $fieldName = $prefix . $metadata->getField();
            $objectFields[$fieldName] = $trans[$transDomain][$fieldName];
        }
        // endregion

        return $objectFields;
    }

    public function addWebsiteSageApi(bool $force = false): bool|string
    {
        // woocommerce/includes/admin/class-wc-admin-meta-boxes.php:134 add_meta_box( 'woocommerce-product-data
        $optionFormSubmitted =
            array_key_exists('settings-updated', $_GET) &&
            array_key_exists('page', $_GET) &&
            $_GET["settings-updated"] === 'true' &&
            $_GET["page"] === Sage::TOKEN . '_settings';
        if (!($force || ($optionFormSubmitted && current_user_can(self::$capability)))) {
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

        $newApplicationPassword = WP_Application_Passwords::create_new_application_password($user_id, [
            'name' => $applicationPasswordOption
        ]);
        $newPassword = $newApplicationPassword[0];
        update_option($applicationPasswordOption, $user_id);
        return $newPassword;
    }

    private function createUpdateWebsite(string $user_id, string $password): bool|string
    {
        $user = get_user_by('id', $user_id);
        $stdClass = $this->sage->sageGraphQl->createUpdateWebsite(
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

        $this->sage->sageGraphQl->updateAllSageEntitiesInOption(ignores: ['getFTaxes']);
        $this->updateTaxes(showMessage: false);
        $this->updateShippingMethodsWithSage();

        add_action('admin_notices', static function (): void {
            ?>
            <div class="notice notice-success is-dismissible"><p><?=
                    __('Connexion réussie à l\'API. Les paramètres ont été mis à jour.', Sage::TOKEN)
                    ?></p></div>
            <?php
        });
        return true;
    }

    public function updateTaxes(bool $showMessage = true): void
    {
        [$taxe, $rates] = $this->getWordpressTaxes();
        $fTaxes = $this->sage->sageGraphQl->getFTaxes(useCache: false, getFromSage: true);
        if (!Sage::showErrors($fTaxes)) {
            $taxeChanges = $this->getTaxesChanges($fTaxes, $rates);
            $this->applyTaxesChanges($taxeChanges);
            if ($showMessage && $taxeChanges !== []) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?= __("Les taxes Sage ont été mises à jour.", Sage::TOKEN) ?></strong></p>
                </div>
                <?php
            }
        }
    }

    public function getWordpressTaxes(): array
    {
        $taxes = WC_Tax::get_tax_rate_classes();
        $taxe = current(array_filter($taxes, static function (stdClass $taxe) {
            return $taxe->slug === Sage::TOKEN;
        }));
        if ($taxe === false) {
            WC_Tax::create_tax_class(__('Sage', Sage::TOKEN), Sage::TOKEN);
            $taxes = WC_Tax::get_tax_rate_classes();
            $taxe = current(array_filter($taxes, static function (stdClass $taxe) {
                return $taxe->slug === Sage::TOKEN;
            }));
        }
        $rates = WC_Tax::get_rates_for_tax_class($taxe->slug);
        return [$taxe, $rates];
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

    private function applyTaxesChanges(array $taxeChanges): void
    {
        foreach ($taxeChanges as $taxeChange) {
            if ($taxeChange["change"] === TaxeUtils::ADD_TAXE_ACTION) {
                WC_Tax::_insert_tax_rate([
                    "tax_rate_country" => "",
                    "tax_rate_state" => "",
                    "tax_rate" => $taxeChange["new"]->taNp === 1 ? 0 : (string)$taxeChange["new"]->taTaux,
                    "tax_rate_name" => $taxeChange["new"]->taCode,
                    "tax_rate_priority" => "1",
                    "tax_rate_compound" => "0",
                    "tax_rate_shipping" => "1",
                    "tax_rate_class" => Sage::TOKEN
                ]);
            } else if ($taxeChange["change"] === TaxeUtils::REMOVE_TAXE_ACTION) {
                WC_Tax::_delete_tax_rate($taxeChange["old"]->tax_rate_id);
            }
        }
    }

    private function updateShippingMethodsWithSage(): void
    {
        // woocommerce/includes/class-wc-ajax.php : shipping_zone_add_method
        $pExpeditions = $this->sage->sageGraphQl->getPExpeditions();
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

    private function showMetaBoxProduct(array $wp_meta_boxes, string $screen): void
    {
        $arRef = Sage::getArRef(get_the_ID());
        $id = 'woocommerce-product-data';
        $context = 'normal';
        remove_meta_box($id, $screen, $context);

        $callback = $wp_meta_boxes[$screen][$context]["high"][$id]["callback"];
        add_meta_box($id, __('Product data', 'woocommerce'), static function (WP_Post $wpPost) use ($arRef, $callback): void {
            ob_start();
            $callback($wpPost);
            $dom = new Dom(); // https://github.com/paquettg/php-html-parser?tab=readme-ov-file#modifying-the-dom
            $dom->loadStr(ob_get_clean());

            $a = $dom->find('span.product-data-wrapper')[0];
            $content = $a->innerHtml();
            $hasArRef = !empty($arRef);
            $labelArRef = '';
            if ($hasArRef) {
                $labelArRef = ': <span style="display: initial" class="h4">' . $arRef . '</span>';
            }
            $content = str_replace($content, $labelArRef . $content, $dom);
            if ($hasArRef || str_contains($wpPost->post_status, 'draft')) {
                $content = str_replace(
                    ["selected='selected'", "option value=" . Sage::TOKEN],
                    ['', "option value=" . Sage::TOKEN . " selected='selected'"],
                    $content
                );
            }
            echo $content;
        }, $screen, $context, 'high');
    }

    /**
     * woocommerce/src/Internal/Admin/Orders/Edit.php:78 add_meta_box('woocommerce-order-data'
     */
    private function showMetaBoxOrder(array $wp_meta_boxes, string $screen): void
    {
        $settings = $this;
        $id = 'woocommerce-order-data';
        $context = 'normal';
        remove_meta_box($id, $screen, $context);

        $callback = $wp_meta_boxes[$screen][$context]["high"][$id]["callback"];
        add_meta_box($id, sprintf(__('%s data', 'woocommerce'), __('Order', 'woocommerce')), static function (WC_Order $order) use ($callback, $settings): void {
            echo $settings->getMetaBoxOrder($order, $callback);
        }, $screen, $context, 'high');
    }

    public function getMetaBoxOrder(WC_Order $order, ?callable $callback = null): string
    {
        ob_start();
        if (is_null($callback)) {
            WC_Meta_Box_Order_Data::output($order);
        } else {
            $callback($order);
        }
        $dom = new Dom(); // https://github.com/paquettg/php-html-parser?tab=readme-ov-file#modifying-the-dom
        $dom->loadStr(ob_get_clean());
        $fDocenteteIdentifier = $this->sage->sageWoocommerce->getFDocenteteIdentifierFromOrder($order);
        $translations = SageTranslationUtils::getTranslations();
        if (!empty($fDocenteteIdentifier)) {
            $a = $dom->find('.woocommerce-order-data__heading')[0];
            $title = $a->innerHtml();
            return str_replace($title, $title . '['
                . $translations["fDocentetes"]["doType"]["values"][$fDocenteteIdentifier["doType"]]
                . ': n° '
                . $fDocenteteIdentifier["doPiece"]
                . ']', $dom);
        }
        return $dom;
    }

    public static function get_option_date_or_null(string $option, bool $default_value = false): ?DateTime
    {
        $dateString = get_option($option, $default_value);
        if (($date = DateTime::createFromFormat('Y-m-d', $dateString)) !== false) {
            return new DateTime($date->format('Y-m-d 00:00:00'));
        }
        if (($date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString)) !== false) {
            return $date;
        }
        return null;
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

    public function getMetaBoxOrderItems(WC_Order $order): string
    {
        ob_start();
        include __DIR__ . '/../../woocommerce/includes/admin/meta-boxes/views/html-order-items.php';
        return ob_get_clean();
    }

    /**
     * We specifically set the default value in bdd in case between an upgrade we change the default value.
     * This way the user we keep the previous value if he never changed it.
     */
    public function applyDefaultSageEntityMenuOptions(bool $force = false): void
    {
        $optionNames = [];
        foreach ($this->sageEntityMenus as $sageEntityMenu) {
            foreach ($sageEntityMenu->getOptions() as $option) {
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

    public function registerOrderSageColumn(): void
    {
        // copy of get_order_screen_id in woocommerce/src/Internal/Orders/OrderAttributionController.php
        $sageSettings = $this;
        $screen_id = $this->get_order_screen_id();
        $add_column = function (array $columns) {
            $columns[Sage::TOKEN] = __('Sage', Sage::TOKEN);
            return $columns;
        };
        // HPOS and non-HPOS use different hooks.
        add_filter("manage_{$screen_id}_columns", $add_column, 11);
        add_filter("manage_edit-{$screen_id}_columns", $add_column, 11);
        $trans = SageTranslationUtils::getTranslations();
        $display_column = function (string $column_name, WC_Order $order) use ($sageSettings, $trans) {
            if (Sage::TOKEN !== $column_name) {
                return;
            }
            $identifier = $sageSettings->sage->sageWoocommerce->getFDocenteteIdentifierFromOrder($order);
            if (empty($identifier)) {
                echo '<span class="dashicons dashicons-no" style="color: red"></span>';
                return;
            }
            echo $trans["fDocentetes"]["doType"]["values"][$identifier['doType']]
                . ': n° '
                . $identifier["doPiece"];
        };
        // HPOS and non-HPOS use different hooks.
        add_action("manage_{$screen_id}_custom_column", $display_column, 10, 2);
        add_action("manage_{$screen_id}_posts_custom_column", $display_column, 10, 2);
    }

    private function get_order_screen_id(): string
    {
        // copy of register_order_origin_column in woocommerce/src/Internal/Orders/OrderAttributionController.php
        return OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
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
