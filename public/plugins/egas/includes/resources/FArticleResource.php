<?php

namespace App\resources;

use App\class\SageEntityMetadata;
use App\enum\Sage\ArticleTypeEnum;
use App\enum\Sage\NomenclatureTypeEnum;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\WoocommerceService;
use App\utils\SageTranslationUtils;
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
        parent::__construct();
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
            Sage::META_DATA_PREFIX . '_last_update',
            Sage::META_DATA_PREFIX . '_postId',
        ];
        $this->mandatoryFields = [
            'arRef', // [IsProjected(true)]
        ];
        $this->filterType = self::FILTER_TYPE;
        $this->transDomain = SageTranslationUtils::TRANS_FARTICLES;
        $initFilter = self::getDefaultResourceFilter();
        $initFilterJson = json_encode($initFilter);
        $this->options = [
            [
                'id' => 'sage_create_new_farticle',
                'label' => __("Créer l'article dans Sage.", Sage::TOKEN),
                'description' => __("Créer l'article dans Sage lorsqu'un nouveau produit Woocommerce est crée.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'sage_create_old_farticle',
                'label' => __("Importe les anciens produits.", Sage::TOKEN),
                'description' => __("Importe les anciens produits Woocommerce dans Sage.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'sage_update_farticle',
                'label' => __("Met à jour l’article Sage.", Sage::TOKEN),
                'description' => __("Met à jour l’article Sage lorsque le produit WooCommerce qui lui est lié est modifié.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'website_create_new_product',
                'label' => __("Créer le produit dans Woocommerce.", Sage::TOKEN),
                'description' => __("Créer le produit dans Woocommerce lorsqu'un nouvel article Sage est crée.", Sage::TOKEN),
                'type' => 'resource',
                'initFilter' => $initFilterJson,
                'default' => '',
            ],
            [
                'id' => 'website_create_old_product',
                'label' => __("Importe les anciens articles.", Sage::TOKEN),
                'description' => __("Importe les anciens articles Sage dans Woocommerce.", Sage::TOKEN),
                'type' => 'resource',
                'initFilter' => json_encode([
                    'values' => [
                        ...$initFilter['values'],
                        [
                            'field' => 'cbCreation',
                            'condition' => 'gte',
                            'value' => '2000-01-01'
                        ]
                    ]
                ]),
                'default' => '',
            ],
            [
                'id' => 'website_update_product',
                'label' => __("Met à jour le produit Woocommerce.", Sage::TOKEN),
                'description' => __("Met à jour le produit Woocommerce lorsque l'article Sage qui lui est lié est modifié.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
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
        $this->importCondition = [
            new ImportConditionDto(
                field: 'arType', // [IsProjected(true)]
                value: [
                    // todo mettre que les articles standard
                    ArticleTypeEnum::ArticleTypeStandard->value,
                    ArticleTypeEnum::ArticleTypeGamme->value
                ],
                condition: 'in',
                message: function ($fArticle) {
                    return __("Seuls les articles standard ou à gamme peuvent être importés.", Sage::TOKEN);
                }
            ),
            new ImportConditionDto(
                field: 'arNomencl', // [IsProjected(true)]
                value: NomenclatureTypeEnum::NomenclatureTypeAucun->value,
                condition: 'eq',
                message: function ($fArticle) {
                    return __("Seuls les articles ayant une nomenclature Aucun peuvent être importés.", Sage::TOKEN);
                }
            ),
        ];
        $this->import = static function (string $identifier) {
            [$response, $responseError, $message, $postId] = WoocommerceService::getInstance()->importFArticleFromSage(
                $identifier,
                ignorePingApi: true,
            );
            return $postId;
        };
        $this->selectionSet = GraphqlService::getInstance()->_getFArticleSelectionSet();
    }

    public static function getDefaultResourceFilter(): array
    {
        return [
            'values' => [
                [
                    'field' => 'arPublie',
                    'condition' => 'eq',
                    'value' => true
                ]
            ]
        ];
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
