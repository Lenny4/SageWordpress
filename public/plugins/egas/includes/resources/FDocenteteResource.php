<?php

namespace App\resources;

use App\class\SageEntityMetadata;
use App\enum\Sage\DomaineTypeEnum;
use App\enum\Sage\TiersTypeEnum;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\WoocommerceService;
use App\utils\SageTranslationUtils;
use stdClass;

class FDocenteteResource extends Resource
{
    public const ENTITY_NAME = 'fDocentetes';
    public const TYPE_MODEL = 'FDocentete';
    public const DEFAULT_SORT = 'doDate';
    public const FILTER_TYPE = 'FDocenteteFilterInput';
    public final const META_KEY = '_' . Sage::TOKEN . '_identifier';

    private static ?FDocenteteResource $instance = null;

    private function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->title = __("Documents", Sage::TOKEN);
        $this->description = __("Gestion Commerciale / Menu Traitement / Documents des ventes, des achats, des stocks et internes / Fenêtre Document", Sage::TOKEN);
        $this->entityName = self::ENTITY_NAME;
        $this->typeModel = self::TYPE_MODEL;
        $this->defaultSortField = self::DEFAULT_SORT;
        $this->defaultFields = [
            'doDomaine',
            'doPiece',
            'doType',
            'doDate',
            Sage::META_DATA_PREFIX . '_last_update',
            Sage::META_DATA_PREFIX . '_postId',
        ];
        $this->mandatoryFields = [
            'doDomaine', // to show import in sage button or not
            'doPiece',
            'doType',
        ];
        $this->filterType = self::FILTER_TYPE;
        $this->transDomain = SageTranslationUtils::TRANS_FDOCENTETES;
        $this->options = [
            [
                'id' => 'auto_create_' . Sage::TOKEN . '_fdocentete',
                'label' => __('Créer automatiquement le document de vente Sage', Sage::TOKEN),
                'description' => __("Créer automatiquement un document de vente dans Sage lorsqu'une commande Woocommerce est crée.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'auto_create_wordpress_order',
                'label' => __('Créer automatiquement la commande Woocommerce', Sage::TOKEN),
                'description' => __("Créer automatiquement une commande dans Woocommerce lorsqu'un document de vente Sage est crée pour les types de documents sélectionnés.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'auto_import_wordpress_order',
                'label' => __('Importer automatiquement les anciens documents de vente Sage', Sage::TOKEN),
                'description' => __("Importe les documents de vente Sage dans Woocommerce à compter de la date renseignée (date de création du compte dans Sage). Laissez vide pour ne pas importer.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
        ];
        $this->metadata = static function (?stdClass $obj = null): array {
            $result = [
                new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
            ];
            return SageService::getInstance()->addSelectionSetAsMetadata(GraphqlService::getInstance()->_getFDocenteteSelectionSet(), $result, $obj);
        };
        $this->metaKeyIdentifier = self::META_KEY;
        $this->metaTable = $wpdb->prefix . 'wc_orders_meta';
        $this->metaColumnIdentifier = 'order_id';
        $this->importCondition = [
            new ImportConditionDto(
                field: 'doDomaine',
                value: DomaineTypeEnum::DomaineTypeVente->value,
                condition: 'eq',
                message: function ($fDocentete) {
                    return __("Seuls les documents de ventes peuvent être importés.", Sage::TOKEN);
                }),
        ];
        $this->import = static function (string $identifier) {
            $data = json_decode(stripslashes($identifier), false, 512, JSON_THROW_ON_ERROR);
            [$message, $order] = WoocommerceService::getInstance()->importFDocenteteFromSage($data->doPiece, $data->doType);
            return $order->get_id();
        };
        $this->selectionSet = GraphqlService::getInstance()->_getFDocenteteSelectionSet();
        $this->getIdentifier = static function (array $fDocentete) {
            return json_encode(['doPiece' => $fDocentete["doPiece"], 'doType' => $fDocentete["doType"]], JSON_THROW_ON_ERROR);
        };
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
