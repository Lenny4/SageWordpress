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
            'ctNum', // [IsProjected(true)]
        ];
        $this->filterType = self::FILTER_TYPE;
        $this->transDomain = SageTranslationUtils::TRANS_FCOMPTETS;
        $this->options = [
            [
                'id' => 'sage_create_new_fcomptet',
                'label' => __("Créer le compte dans Sage.", Sage::TOKEN),
                'description' => __("Créer le compte dans Sage lorsqu'un nouveau utilisateur Wordpress est crée.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'sage_create_old_fcomptet',
                'label' => __("Importe les anciens utilisateurs.", Sage::TOKEN),
                'description' => __("Importe les anciens utilisateurs Woocommerce dans Sage.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'sage_update_fcomptet',
                'label' => __("Met à jour le compte Sage.", Sage::TOKEN),
                'description' => __("Met à jour le compte Sage lorsque l'utilisateur WooCommerce qui lui est lié est modifié.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'website_create_new_user',
                'label' => __("Créer l'utilisateur dans Woocommerce.", Sage::TOKEN),
                'description' => __("Créer l'utilisateur dans Woocommerce lorsqu'un nouveau compte Sage est crée.", Sage::TOKEN),
                'type' => 'resource',
                'default' => '',
            ],
            [
                'id' => 'website_create_old_user',
                'label' => __("Importe les anciens comptes Sage.", Sage::TOKEN),
                'description' => __("Importe les anciens comptes Sage dans Woocommerce.", Sage::TOKEN),
                'type' => 'resource',
                'default' => '',
            ],
            [
                'id' => 'website_update_user',
                'label' => __("Met à jour l'utilisateur Woocommerce.", Sage::TOKEN),
                'description' => __("Met à jour l'utilisateur Woocommerce lorsque le compte Sage qui lui est lié est modifié.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
            ],
            [
                'id' => 'mail_website_create_new_user',
                'label' => __('Envoyer automatiquement le mail pour définir le mot de passe', Sage::TOKEN),
                'description' => __("Lorsqu'un compte Wordpress est créé à partir d'un compte Sage, un mail pour définir le mot de passe du compte Wordpress est automatiquement envoyé à l'utilisateur.", Sage::TOKEN),
                'type' => 'checkbox',
                'default' => 'off',
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
                field: 'ctType', // [IsProjected(true)]
                value: TiersTypeEnum::TiersTypeClient->value,
                condition: 'eq',
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
