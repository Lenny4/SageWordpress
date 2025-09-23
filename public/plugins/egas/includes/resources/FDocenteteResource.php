<?php

namespace App\resources;

use App\class\SageEntityMetadata;
use App\Sage;
use App\Utils\SageTranslationUtils;
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
        ];
        $this->metadata = static function (?stdClass $obj = null): array {
            $result = [
                new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
            ];
            return $sageSettings->addSelectionSetAsMetadata($sageGraphQl->_getFDocenteteSelectionSet(), $result, $obj);
        };
        $this->metaKeyIdentifier = self::META_KEY;
        $this->metaTable = $wpdb->prefix . 'wc_orders_meta';
        $this->metaColumnIdentifier = 'order_id';
        $this->canImport = static function (array $fDocentete) {
            return $sageWoocommerce->canImportOrderFromSage((object)$fDocentete);
        };
        $this->import = static function (string $identifier) {
            $data = json_decode(stripslashes($identifier), false, 512, JSON_THROW_ON_ERROR);
            [$message, $order] = $sageWoocommerce->importFDocenteteFromSage($data->doPiece, $data->doType);
            return $order->get_id();
        };
        $this->selectionSet = $sageGraphQl->_getFDocenteteSelectionSet();
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
}
