<?php

namespace App\resources;

use App\class\SageEntityMetadata;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\WoocommerceService;
use App\Utils\SageTranslationUtils;
use DateTime;
use stdClass;

class FArticleResource extends Resource
{
    public const ENTITY_NAME = 'fArticles';
    public const TYPE_MODEL = 'FArticle';
    public const DEFAULT_SORT = 'arRef';
    public const FILTER_TYPE = 'FArticleFilterInput';
    public final const META_KEY = '_' . Sage::TOKEN . '_arRef';

    private static ?FArticleResource $instance = null;

    private function __construct()
    {
        global $wpdb;
        $this->title = __("Articles", Sage::TOKEN);
        $this->description = __("Gestion des articles", Sage::TOKEN);
        $this->entityName = self::ENTITY_NAME;
        $this->typeModel = self::TYPE_MODEL;
        $this->defaultSortField = self::DEFAULT_SORT;
        $this->defaultFields = [
            'arRef',
            'arDesign',
            'arType',
            'arPublie',
            Sage::META_DATA_PREFIX . '_last_update',
            Sage::META_DATA_PREFIX . '_postId',
        ];
        $this->mandatoryFields = [
            'arRef',
            'arType', // to show import in sage button or not
            'arNomencl', // to show import in sage button or not
            'arPublie', // to show import in sage button or not
        ];
        $this->filterType = self::FILTER_TYPE;
        $this->transDomain = SageTranslationUtils::TRANS_FARTICLES;
        $this->options = [
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
        ];
        $this->metadata = static function (?stdClass $obj = null): array {
            $result = [
                new SageEntityMetadata(field: '_prices', value: static function (StdClass $fArticle) {
                    return json_encode($fArticle->prices, JSON_THROW_ON_ERROR);
                }),
                new SageEntityMetadata(field: '_max_price', value: static function (StdClass $fArticle) {
                    return json_encode(WoocommerceService::getInstance()->getMaxPrice($fArticle->prices), JSON_THROW_ON_ERROR);
                }),
                new SageEntityMetadata(field: '_last_update', value: static function (StdClass $fArticle) {
                    return (new DateTime())->format('Y-m-d H:i:s');
                }, showInOptions: true),
                new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
                new SageEntityMetadata(field: '_canEditArSuiviStock', value: static function (StdClass $fArticle) {
                    return $fArticle->canEditArSuiviStock;
                }),
            ];
            return SageService::getInstance()->addSelectionSetAsMetadata(GraphqlService::getInstance()->_getFArticleSelectionSet(), $result, $obj);
        };
        $this->metaKeyIdentifier = self::META_KEY;
        $this->metaTable = $wpdb->postmeta;
        $this->metaColumnIdentifier = 'post_id';
        $this->canImport = static function (array $fArticle) {
            return WoocommerceService::getInstance()->canImportFArticle((object)$fArticle);
        };
        $this->import = static function (string $identifier) {
            [$response, $responseError, $message, $postId] = WoocommerceService::getInstance()->importFArticleFromSage(
                $identifier,
                ignorePingApi: true,
            );
            return $postId;
        };
        $this->selectionSet = GraphqlService::getInstance()->_getFArticleSelectionSet();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function supports(): bool
    {
        return true;
    }
}
