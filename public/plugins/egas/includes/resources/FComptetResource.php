<?php

namespace App\resources;

use App\class\SageEntityMetadata;
use App\enum\Sage\TiersTypeEnum;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\utils\SageTranslationUtils;
use DateTime;
use stdClass;

class FComptetResource extends Resource
{
    public const ENTITY_NAME = 'fComptets';
    public const TYPE_MODEL = 'FComptet';
    public const DEFAULT_SORT = 'ctNum';
    public const FILTER_TYPE = 'FComptetFilterInput';
    public final const META_KEY = '_' . Sage::TOKEN . '_ctNum';
    private static ?FComptetResource $instance = null;

    private function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->title = __("Clients", Sage::TOKEN);
        // todo afficher les clients Sage qui partagent le même email et expliqués qu'il ne seront pas dupliqués sur le site
        $this->description = __("Gestion des clients.", Sage::TOKEN);
        $this->entityName = self::ENTITY_NAME;
        $this->typeModel = self::TYPE_MODEL;
        $this->defaultSortField = self::DEFAULT_SORT;
        $this->defaultFields = [
            'ctNum',
            'ctIntitule',
            'ctContact',
            'ctEmail',
            Sage::META_DATA_PREFIX . '_last_update',
            Sage::META_DATA_PREFIX . '_postId',
        ];
        $this->mandatoryFields = [
            'ctNum',
            'ctType', // to show import in sage button or not
        ];
        $this->filterType = self::FILTER_TYPE;
        $this->transDomain = SageTranslationUtils::TRANS_FCOMPTETS;
        $this->options = [
            [
                'id' => 'auto_create_sage_fcomptet',
                'label' => __('Créer automatiquement le client Sage', Sage::TOKEN),
                'description' => __("Créer automatiquement un compte client dans Sage lorsqu'un compte Wordpress est crée.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'auto_import_sage_fcomptet',
                'label' => __('Importer automatiquement les anciens clients Woocommerce', Sage::TOKEN),
                'description' => __("Importe les comptes Woocommerce dans Sage à compter de la date renseignée (date de création du compte dans Woocommerce). Laissez vide pour ne pas importer.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'auto_create_wordpress_account',
                'label' => __('Créer automatiquement le compte Wordpress', Sage::TOKEN),
                'description' => __("Créer automatiquement un compte dans Wordpress lorsqu'un utilisateur Sage est crée.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'auto_import_wordpress_account',
                'label' => __('Importer automatiquement les anciens clients Sage', Sage::TOKEN),
                'description' => __("Importe les comptes Sage dans Woocommerce à compter de la date renseignée (date de création du compte dans Sage). Laissez vide pour ne pas importer.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'mail_auto_create_sage_fcomptet',
                'label' => __('Envoyer automatiquement le mail pour définir le mot de passe', Sage::TOKEN),
                'description' => __("Lorsqu'un compte Wordpress est créé à partir d'un compte Sage, un mail pour définir le mot de passe du compte Wordpress est automatiquement envoyé à l'utilisateur.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off'
            ],
            [
                'id' => 'auto_update_sage_fcomptet_when_edit_account',
                'label' => __("Mettre à jour automatiquement un compte Sage lorsqu'un compte Wordpress est modifié", Sage::TOKEN),
                'description' => __("Lorsque qu’un utilisateur WordPress met à jour ses informations, ou lorsqu’un administrateur modifie les informations d’un compte WordPress, celles-ci sont également mises à jour dans Sage si un compte y est lié.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
            [
                'id' => 'auto_update_account_when_edit_sage_fcomptet',
                'label' => __("Mettre à jour automatiquement un compte Wordpress lorsqu'un compte Sage est modifié", Sage::TOKEN),
                'description' => __("Lorsque les informations d’un compte Sage sont modifiées, elles sont également mises à jour dans WordPress si un compte y est lié.", Sage::TOKEN),
                'type' => 'resource',
                'default' => 'off'
            ],
        ];
        $this->metadata = static function (?stdClass $obj = null): array {
            $result = [
                new SageEntityMetadata(field: '_last_update', value: static function (StdClass $fComptet) {
                    return (new DateTime())->format('Y-m-d H:i:s');
                }, showInOptions: true),
                new SageEntityMetadata(field: '_postId', value: null, showInOptions: true),
            ];
            return SageService::getInstance()->addSelectionSetAsMetadata(GraphqlService::getInstance()->_getFComptetSelectionSet(), $result, $obj);
        };
        $this->metaKeyIdentifier = self::META_KEY;
        $this->metaTable = $wpdb->usermeta;
        $this->metaColumnIdentifier = 'user_id';
        $this->importCondition = [
            new ImportConditionDto(
                field: 'ctType',
                value: TiersTypeEnum::TiersTypeClient->value,
                message: function ($fComptet) {
                    return __("Le compte n'est pas un compte client.", Sage::TOKEN);
                }),
        ];
        $this->import = static function (string $identifier) {
            [$userId, $message] = SageService::getInstance()->updateUserOrFComptet($identifier, ignorePingApi: true);
            return $userId;
        };
        $this->selectionSet = GraphqlService::getInstance()->_getFComptetSelectionSet();
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
