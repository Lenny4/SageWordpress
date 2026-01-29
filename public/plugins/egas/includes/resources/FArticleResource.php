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
            'arRef',
        ];
        $this->filterType = self::FILTER_TYPE;
        $this->transDomain = SageTranslationUtils::TRANS_FARTICLES;
        $this->options = function (): array {
            // todo
//            $websiteApi = SageService::getInstance()->getWebsiteOption();
            $initFilter = self::getDefaultResourceFilter();
            $initFilterJson = json_encode($initFilter, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            return [
                [
                    'id' => 'sage_create_new_' . self::ENTITY_NAME,
                    'label' => __("Créer l'article dans Sage.", Sage::TOKEN),
                    'description' => __("Créer l'article dans Sage lorsqu'un nouveau produit Woocommerce est crée.", Sage::TOKEN),
                    'type' => 'checkbox',
                    'default' => 'off',
                ],
//            [
//                'id' => 'sage_create_old_' . self::ENTITY_NAME,
//                'label' => __("Importe les anciens produits.", Sage::TOKEN),
//                'description' => __("Importe les anciens produits Woocommerce dans Sage.", Sage::TOKEN),
//                'type' => 'checkbox',
//                'default' => 'off',
//            ],
                [
                    'id' => 'sage_update_' . self::ENTITY_NAME,
                    'label' => __("Met à jour l’article Sage.", Sage::TOKEN),
                    'description' => __("Met à jour l’article Sage lorsque le produit WooCommerce qui lui est lié est modifié.", Sage::TOKEN),
                    'type' => 'checkbox',
                    'default' => 'off',
                ],
                [
                    'id' => 'website_create_new_' . self::ENTITY_NAME,
                    'label' => __("Créer le produit dans Woocommerce.", Sage::TOKEN),
                    'description' => __("Créer le produit dans Woocommerce lorsqu'un nouvel article Sage est crée.", Sage::TOKEN),
                    'type' => 'resource',
                    'initFilter' => $initFilterJson,
                    'default' => '',
                ],
                [
                    'id' => 'website_create_old_' . self::ENTITY_NAME,
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
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'default' => '',
                ],
                [
                    'id' => 'website_update_' . self::ENTITY_NAME,
                    'label' => __("Met à jour le produit Woocommerce.", Sage::TOKEN),
                    'description' => __("Met à jour le produit Woocommerce lorsque l'article Sage qui lui est lié est modifié.", Sage::TOKEN),
                    'type' => 'checkbox',
                    'default' => 'off',
                ],
                // todo ajouter une option pour considérer les catalogues comme des catégories
            ];
        };
        $this->metadata = function (?stdClass $obj = null): array {
            $result = [
                ...$this->getMandatoryMetadata(),
                new SageEntityMetadata(field: '_prices', value: static function (StdClass $fArticle) {
                    return json_encode($fArticle->prices, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }),
                new SageEntityMetadata(field: '_max_price', value: static function (StdClass $fArticle) {
                    return json_encode(WoocommerceService::getInstance()->getMaxPrice($fArticle->prices), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                }),
                new SageEntityMetadata(field: '_canEditArSuiviStock', value: static function (StdClass $fArticle) {
                    return $fArticle->canEditArSuiviStock;
                }),
            ];
            return SageService::getInstance()->addSelectionSetAsMetadata(GraphqlService::getInstance()->_getFArticleSelectionSet(), $result, $obj);
        };
        $this->bddMetadata = function (?int $productId, bool $clearCache = false): array {
            if (empty($productId)) {
                return [];
            }
            if ($clearCache) {
                clean_post_cache($productId);
            }
            return SageService::getInstance()->get_post_meta_single($productId);
        };
        $this->sageEntity = function (?string $arRef): StdClass|null {
            return GraphqlService::getInstance()->getFArticle($arRef);
        };
        $this->importFromSage = function (?string $arRef, stdClass|string|null $fArticle = null, $showSuccessMessage = true): array|string {
            return WoocommerceService::getInstance()->importFArticleFromSage($arRef, showSuccessMessage: $showSuccessMessage);
        };
        $this->metaKeyIdentifier = self::META_KEY;
        $this->table = $wpdb->posts;
        $this->metaTable = $wpdb->postmeta;
        $this->metaColumnIdentifier = 'post_id';
        $this->postType = 'product';
        $this->importCondition = [
            new ImportConditionDto(
                field: 'arType',
                value: [
                    ArticleTypeEnum::ArticleTypeStandard->value,
                ],
                condition: 'in',
                message: function (array $fArticle): string {
                    return __("Seuls les articles standard peuvent être importés.", Sage::TOKEN) . ' [' . $fArticle["arRef"] . ']';
                }
            ),
            new ImportConditionDto(
                field: 'arNomencl',
                value: NomenclatureTypeEnum::NomenclatureTypeAucun->value,
                condition: 'eq',
                message: function (array $fArticle): string {
                    return __("Seuls les articles ayant une nomenclature Aucun peuvent être importés.", Sage::TOKEN) . ' [' . $fArticle["arRef"] . ']';
                }
            ),
        ];
        $this->import = static function (string $identifier) {
            [$response, $responseError, $message, $postId] = WoocommerceService::getInstance()->importFArticleFromSage(
                $identifier,
            );
            return $postId;
        };
        $this->selectionSet = function (): array {
            return GraphqlService::getInstance()->_getFArticleSelectionSet();
        };
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
