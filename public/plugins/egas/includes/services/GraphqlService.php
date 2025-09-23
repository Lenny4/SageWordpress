<?php

namespace App\services;

use App\class\Dto\ArgumentSelectionSetDto;
use App\Sage;
use App\Utils\FDocenteteUtils;
use App\Utils\PCatComptaUtils;
use App\Utils\SageTranslationUtils;
use GraphQL\Client;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\RawObject;
use GraphQL\Variable;
use stdClass;

if (!defined('ABSPATH')) {
    exit;
}

class GraphqlService
{
    private static ?GraphqlService $instance = null;
    private ?Client $client = null;
    private bool $pingApi = false;
    private ?array $pExpeditions = null;
    private ?array $fFamilles = null;
    private ?array $pUnites = null;
    private ?array $pCatComptas = null;
    private ?array $pCattarifs = null;
    private ?array $fPays = null;
    private ?array $fTaxes = null;
    private ?stdClass $pDossier = null;
    private ?stdClass $pPreference = null;
    private ?array $fCatalogues = null;
    private ?array $fGlossaires = null;
    private ?array $cbSysLibres = null;
    private ?array $fDepots = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct(public ?string $file = '')
    {
        if (is_admin()) {
            $this->ping();
        }
    }

    public function createUpdateWebsite(
        string $username,
        string $password,
        bool   $getError = false,
    ): StdClass|null|string
    {
        global $wpdb;
        $hasError = false;
        $wordpressHostUrl = parse_url((string)get_option(Sage::TOKEN . '_wordpress_host_url'));
        if (!array_key_exists("scheme", $wordpressHostUrl)) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="error"><p>
                    <?= __("Wordpress host url doit commencer par 'http://' ou 'https://'", Sage::TOKEN) ?>
                </p>
                </div><?php
            });
            $hasError = true;
        }
        $apiHostUrl = parse_url((string)get_option(Sage::TOKEN . '_api_host_url'));
        if (!array_key_exists("scheme", $apiHostUrl)) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="error"><p>
                    <?= __("Api host url doit commencer par 'http://' ou 'https://'", Sage::TOKEN) ?>
                </p>
                </div><?php
            });
            $hasError = true;
        }
        if ($hasError) {
            return null;
        }
        $mutation = (new Mutation('createUpdateWebsite'))
            ->setVariables([new Variable('websiteDto', 'WebsiteDtoInput', true)])
            ->setArguments(['websiteDto' => '$websiteDto'])
            ->setSelectionSet(
                [
                    'id',
                    'authorization',
                ]
            );
        $variables = [
            'websiteDto' => [
                'name' => get_bloginfo(),
                'username' => $username,
                'password' => $password,
                'type' => strtoupper(WebsiteEnum::Wordpress->name),
                'host' => $wordpressHostUrl["host"],
                'protocol' => $wordpressHostUrl["scheme"],
                'forceSsl' => (bool)get_option(Sage::TOKEN . '_activate_https_verification_wordpress'),
                'dbHost' => get_option(Sage::TOKEN . '_wordpress_db_host'),
                'dbUsername' => get_option(Sage::TOKEN . '_wordpress_db_username'),
                'dbPassword' => get_option(Sage::TOKEN . '_wordpress_db_password'),
                'tablePrefix' => $wpdb->prefix,
                'dbName' => get_option(Sage::TOKEN . '_wordpress_db_name'),
                'autoCreateSageFcomptet' => (bool)get_option(Sage::TOKEN . '_auto_create_' . Sage::TOKEN . '_fcomptet'),
                'autoImportSageFcomptet' => SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_' . Sage::TOKEN . '_fcomptet')?->format('Y-m-d H:i:s'),
                'autoCreateWebsiteAccount' => (bool)get_option(Sage::TOKEN . '_auto_create_wordpress_account'),
                'autoImportWebsiteAccount' => SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_wordpress_account')?->format('Y-m-d H:i:s'),
                'autoCreateSageFdocentete' => (bool)get_option(Sage::TOKEN . '_auto_create_' . Sage::TOKEN . '_fdocentete'),
                'autoImportWebsiteOrderDate' => SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_wordpress_order_date')?->format('Y-m-d H:i:s'),
                'autoImportWebsiteOrderDoType' => get_option(Sage::TOKEN . '_auto_import_wordpress_order_dotype', null),
                'autoCreateWebsiteOrder' => get_option(Sage::TOKEN . '_auto_create_wordpress_order', null),
                'autoCreateWebsiteArticle' => (bool)get_option(Sage::TOKEN . '_auto_create_wordpress_article'),
                'autoImportWebsiteArticle' => SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_wordpress_article')?->format('Y-m-d H:i:s'),
                'pluginVersion' => get_plugin_data($this->sage->file)['Version'],
                'autoUpdateSageFComptetWhenEditAccount' => (bool)get_option(Sage::TOKEN . '_auto_update_' . Sage::TOKEN . '_fcomptet_when_edit_account'),
                'autoUpdateAccountWhenEditSageFcomptet' => (bool)get_option(Sage::TOKEN . '_auto_update_account_when_edit_' . Sage::TOKEN . '_fcomptet'),
            ]
        ];
        return $this->runQuery($mutation, $getError, $variables);
    }

    private function runQuery(
        Query|Mutation $gql,
        bool           $getError = false,
        array          $variables = []
    ): array|object|null|string
    {
        $client = $this->getClient();
        try {
            return $client->runQuery($gql, variables: $variables)?->getResults();
        } catch (Throwable $throwable) {
            // todo store logs
            $message = $throwable->getMessage();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message = json_encode([
                    'message' => $message,
                    'stackTrace' => $throwable->getTraceAsString(),
                ], JSON_THROW_ON_ERROR);
            }
            if ($getError) {
                return $message;
            }
            add_action('admin_notices', static function () use ($message): void {
                ?>
                <div class="error"><p>
                        <?= $message ?>
                    </p></div>
                <?php
            });
        }

        return null;
    }

    private function getClient(): Client
    {
        if (is_null($this->client)) {
            $this->client = new Client(
                get_option(Sage::TOKEN . '_api_host_url') . '/graphql',
                ['Api-Key' => get_option(Sage::TOKEN . '_api_key')],
                [
                    'verify' => (bool)get_option(Sage::TOKEN . '_activate_https_verification_graphql'),
                    'timeout' => 10, // vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php
                ]
            );
        }

        return $this->client;
    }

    public function createUpdateFComptet(
        int     $userId,
        ?string $ctNum = null,
        bool    $new = false,
        bool    $getError = false,
    ): StdClass|null|string
    {
        $autoGenerateCtNum = is_null($ctNum);
        $user = get_user_by('id', $userId);
        $userMetaWordpress = get_user_meta($userId);
        $ctEmail = $user->user_email;
        $ctIntitule = '';
        if (isset($userMetaWordpress['first_name'][0])) {
            $ctIntitule = trim($userMetaWordpress['first_name'][0]);
        }
        if (isset($userMetaWordpress['last_name'][0])) {
            $ctIntitule = trim($userMetaWordpress['first_name'][0]);
        }
        if ($ctIntitule === '') {
            $ctIntitule = $user->data->user_login;
        }
        $arguments = [
            'ctIntitule' => $ctIntitule,
            'ctEmail' => $ctEmail,
            'new' => $new,
            'websiteId' => get_option(Sage::TOKEN . '_website_id'),
            'autoGenerateCtNum' => $autoGenerateCtNum,
        ];
        if (!is_null($ctNum)) {
            $arguments['ctNum'] = $ctNum;
        }
        $mutation = (new Mutation('createUpdateFComptet'))
            ->setVariables([new Variable('createUpdateFComptetDto', 'CreateFComptetDtoInput', true)])
            ->setArguments(['createUpdateFComptetDto' => '$createUpdateFComptetDto'])
            ->setSelectionSet($this->formatSelectionSet($this->_getFComptetSelectionSet()));
        $variables = ['createUpdateFComptetDto' => $arguments];
        $result = $this->runQuery($mutation, $getError, $variables);

        if (!is_null($result) && !is_string($result)) {
            return $result->data->createUpdateFComptet;
        }
        return $result;
    }

    private function formatSelectionSet(array $selectionSets): array
    {
        $result = [];
        foreach ($selectionSets as $key => $value) {
            if (is_numeric($key)) {
                if (!str_starts_with($value['name'], SageSettings::PREFIX_META_DATA)) {
                    $result[] = $value['name'];
                }
            } else {
                $query = (new Query($key));
                if ($value instanceof ArgumentSelectionSetDto) {
                    $result[] = $query
                        ->setArguments($value->getArguments())
                        ->setSelectionSet($this->formatSelectionSet($value->getSelectionSet()));
                } else {
                    $result[] = $query->setSelectionSet($this->formatSelectionSet($value));
                }
            }
        }
        return $result;
    }

    public function _getFComptetSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'ctNum',
                'ctIntitule',
                'ctEmail',
                'ctContact',
                'ctAdresse',
                'ctComplement',
                'ctVille',
                'ctCodePostal',
                'ctPays',
                'ctPaysCode',
                'ctTelephone',
                'ctCodeRegion',
                'nCatTarif',
                'nCatCompta',
            ]),
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'ctType',
            ]),
            'fLivraisons' => new ArgumentSelectionSetDto($this->_getFLivraisonSelectionSet(), 'liNo'),
        ];
    }

    private function _formatOperationFilterInput(string $type, array $fields): array
    {
        return array_map(static function (string $field) use ($type) {
            return [
                "name" => $field,
                "type" => $type,
            ];
        }, $fields);
    }

    public function _getFLivraisonSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'liNo',
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'liIntitule',
                'liAdresse',
                'liComplement',
                'liCodePostal',
                'liPrincipal',
                'liVille',
                'liPays',
                'liPaysCode',
                'liContact',
                'liTelephone',
                'liEmail',
                'liAdresseFact',
                'liCodeRegion',
            ]),
        ];
    }

    public function getTypeModel(string $object): array|null
    {
        $cacheName = 'TypeModel_' . $object;
        if (!$this->pingApi) {
            $result = $this->sage->cache->get($cacheName, static fn() => null);
            if (is_null($result)) {
                $this->sage->cache->delete($cacheName);
            }
            return $result;
        }

        $sageGraphQl = $this;
        $function = static function () use ($object, $sageGraphQl) {
            // https://graphql.org/learn/introspection/
            $query = (new Query('__type'))
                ->setArguments(['name' => $object])
                ->setSelectionSet(
                    [
                        'name',
                        (new Query('fields'))
                            ->setSelectionSet(
                                [
                                    'name',
                                    'description',
                                    (new Query('type'))
                                        ->setSelectionSet(
                                            [
                                                'name',
                                                'kind',
                                                (new Query('ofType'))
                                                    ->setSelectionSet(
                                                        [
                                                            'name',
                                                            'kind',
                                                        ]
                                                    ),
                                            ]
                                        ),
                                ],
                            ),
                    ]
                );
            return $sageGraphQl->runQuery($query)?->data?->__type?->fields;
        };
        $typeModel = $this->sage->cache->get($cacheName, $function);
        if (empty($typeModel)) {
            $this->sage->cache->delete($cacheName);
            $typeModel = $this->sage->cache->get($cacheName, $function);
        }

        return $typeModel;
    }

    public function getPDossier(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): stdClass|null|string
    {
        if (!is_null($this->pDossier) && $getFromSage !== true) {
            return $this->pDossier;
        }
        $entityName = SageEntityMenu::PDOSSIER_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "paged" => "1",
            "per_page" => "1",
            "sort" => '{"cbMarq": "asc"}',
        ];
        $selectionSets = $this->_getPDossierSelectionSet();
        $pDossier = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
        );
        if (is_array($pDossier) && count($pDossier) === 1) {
            $pDossier = $pDossier[0];
        }
        $this->pDossier = $pDossier;
        return $this->pDossier;
    }

    public function _getPDossierSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", ['dRaisonSoc']),
            'nDeviseCompteNavigation' => [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", ['dCodeIso']),
            ],
        ];
    }

    private function getEntitiesAndSaveInOption(
        ?string $cacheName,
        ?bool   $getFromSage,
        string  $entityName,
        array   $queryParams,
        array   $selectionSets,
        bool    $getError,
        bool    $ignorePingApi,
        bool    $allPages = false,
        ?string $arrayKey = null,
    ): array|null|string
    {
        $entities = null;
        $tryGetOption = false;
        $optionName = Sage::TOKEN . '_' . $entityName;
        if (is_null($getFromSage)) {
            $getFromSage = is_admin();
        }
        if (!$getFromSage) {
            $entities = get_option($optionName, null);
            if (!is_null($entities)) {
                $entities = (array)json_decode($entities, false, 512, JSON_THROW_ON_ERROR);
            }
            $tryGetOption = true;
        }
        if (is_null($entities)) {
            $entities = null;
            if ($allPages) {
                $queryParams["paged"] = 0;
                do {
                    $queryParams["paged"]++;
                    $result = $this->searchEntities(
                        $entityName,
                        $queryParams,
                        $selectionSets,
                        $cacheName . '_' . $queryParams["paged"],
                        $getError,
                        $ignorePingApi,
                        $getFromSage,
                        $arrayKey,
                    );

                    if (is_null($result) || is_string($result)) {
                        $entities = $result;
                        break;
                    }

                    $newItems = $result->data->{$entityName}->items;
                    if (is_null($entities)) {
                        $entities = $result;
                    } else {
                        $entities->data->{$entityName}->items = [
                            ...$entities->data->{$entityName}->items,
                            ...$newItems,
                        ];
                    }
                    if (empty($newItems)) {
                        break; // just in case
                    }
                } while (count($result->data->{$entityName}->items) < $result->data->{$entityName}->totalCount);
            } else {
                $entities = $this->searchEntities(
                    $entityName,
                    $queryParams,
                    $selectionSets,
                    $cacheName,
                    $getError,
                    $ignorePingApi,
                    $getFromSage,
                    $arrayKey
                );
            }
            if (is_null($entities) || is_string($entities)) {
                if (!$tryGetOption) {
                    $entitiesBdd = get_option($optionName, null);
                    if ($entitiesBdd !== 'null' && $entitiesBdd !== null) {
                        $entities = (array)json_decode($entitiesBdd, false, 512, JSON_THROW_ON_ERROR);
                    }
                }
            } else {
                $getFromSage = true;
                $entities = $entities->data->{$entityName}->items;
            }
        }
        if ($getFromSage) {
            update_option($optionName, json_encode($entities, JSON_THROW_ON_ERROR));
        }
        return $entities;
    }

    public function searchEntities(
        string  $entityName,
        array   $queryParams,
        array   $selectionSets,
        ?string $cacheName = null,
        bool    $getError = false,
        bool    $ignorePingApi = false,
        bool    $getFromSage = true,
        ?string $arrayKey = null,
    ): StdClass|null|string
    {
        if (!is_null($cacheName)) {
            $cacheName = 'SearchEntities_' . $cacheName;
        }
        if (!$this->pingApi && !$ignorePingApi) {
            if (!is_null($cacheName)) {
                $result = $this->sage->cache->get($cacheName, static fn() => null);
                if (is_null($result)) {
                    $this->sage->cache->delete($cacheName);
                }
                return $result;
            }
            return null;
        }

        $sageGraphQl = $this;
        $function = static function () use ($entityName, $queryParams, $selectionSets, $getError, $sageGraphQl) {
            $nbPerPage = (int)($queryParams["per_page"] ?? SageSettings::$defaultPagination);
            $page = (int)($queryParams["paged"] ?? 1);
            $where = [];
            if (array_key_exists('filter_field', $queryParams)) {
                $primaryFields = array_filter($selectionSets, static function (array|ArgumentSelectionSetDto $field): bool {
                    if ($field instanceof ArgumentSelectionSetDto) {
                        return array_key_exists('name', $field->getSelectionSet());
                    }
                    return array_key_exists('name', $field);
                });
                foreach ($queryParams["filter_field"] as $index => $field) {
                    $fieldValue = $queryParams["filter_value"][$index];
                    $fieldType = $queryParams["filter_type"][$index];
                    $inputType = current(array_filter($primaryFields, static fn(array $f): bool => $f['name'] === $field));
                    if ($inputType !== false) {
                        $inputType = $inputType['type'];
                    }
                    // region format fieldValue
                    if (in_array($inputType, [
                        'StringOperationFilterInput',
                        'DateTimeOperationFilterInput',
                        'UuidOperationFilterInput',
                    ])) {
                        $fieldValue = '"' . $fieldValue . '"';
                    }
                    if (in_array($fieldType, ['in', 'nin'])) {
                        if (str_starts_with($field, '"') && str_ends_with($field, '"')) {
                            $fieldValue = str_replace(',', '","', $fieldValue);
                        }
                        $fieldValue = '[' . $fieldValue . ']';
                    }
                    // endregion
                    if ($fieldType === "object") {
                        // https://stackoverflow.com/a/66316611/6824121
                        $where[] = preg_replace('/"([^"]+)"\s*:\s*/', '$1:', json_encode($fieldValue, JSON_THROW_ON_ERROR));
                    } else if ($fieldValue !== '') {
                        $where[] = '{ ' . $field . ': { ' . $fieldType . ': ' . $fieldValue . ' } }';
                    }
                }
            }

            $order = null;
            [$sortField, $sortValue] = self::getSortField($queryParams);
            if (!is_null($sortField)) {
                $order = '{ ' . $sortField . ': ' . strtoupper((string)$sortValue) . ' }';
            }

            $arguments = [
                'skip' => $nbPerPage * ($page - 1),
                'take' => $nbPerPage,
            ];
            if (!is_null($order)) {
                $arguments['order'] = new RawObject($order);
            }

            if ($where !== []) {
                $arguments['where'] = new RawObject($sageGraphQl->buildGraphQLWhereClause(
                    $sageGraphQl->getGraphQLWhereClause($where, $queryParams["where_condition"] ?? null)[0]
                ));
            }

            $query = (new Query($entityName))
                ->setArguments($arguments)
                ->setSelectionSet(
                    [
                        'totalCount',
                        (new Query('items'))
                            ->setSelectionSet($sageGraphQl->formatSelectionSet($selectionSets)),
                    ]
                );
            return $sageGraphQl->runQuery($query, $getError);
        };
        if (is_null($cacheName)) {
            $results = $function();
        } else {
            if ($getFromSage) {
                $this->sage->cache->delete($cacheName);
            }
            $results = $this->sage->cache->get($cacheName, $function);
            if (empty($results) || is_string($results)) { // if $results is string it means it's an error
                $this->sage->cache->delete($cacheName);
                $results = $this->sage->cache->get($cacheName, $function);
            }
        }

        if (isset($results->data->{$entityName}->items)) {
            $this->addKeysToCollection($results->data->{$entityName}->items, $selectionSets, $arrayKey);
        }
        return $results;
    }

    public static function getSortField(array $queryParams): array
    {
        $defaultSortValue = 'asc';
        if (array_key_exists('sort', $queryParams)) {
            $json = json_decode(stripslashes((string)$queryParams['sort']), true, 512, JSON_THROW_ON_ERROR);
            $sortField = array_key_first($json);
            return [$sortField, (string)$json[$sortField]];
        }

        if (array_key_exists('page', $queryParams)) {
            if ($queryParams['page'] === Sage::TOKEN . '_' . SageEntityMenu::FDOCENTETE_ENTITY_NAME) {
                return [SageEntityMenu::FDOCENTETE_DEFAULT_SORT, 'desc'];
            }

            if ($queryParams['page'] === Sage::TOKEN . '_' . SageEntityMenu::FCOMPTET_ENTITY_NAME) {
                return [SageEntityMenu::FCOMPTET_DEFAULT_SORT, $defaultSortValue];
            }

            if ($queryParams['page'] === Sage::TOKEN . '_' . SageEntityMenu::FARTICLE_ENTITY_NAME) {
                return [SageEntityMenu::FARTICLE_DEFAULT_SORT, $defaultSortValue];
            }

            throw new Exception("Unknown page " . $queryParams['page']);
        }

        return [null, $defaultSortValue];
    }

    private function buildGraphQLWhereClause(array $filter): string
    {
        if (!isset($filter['condition'], $filter['values']) || !is_array($filter['values'])) {
            return '';
        }

        $logicalOperator = strtolower($filter['condition']) === 'or' ? 'or' : 'and';

        $conditions = array_map(function ($value) {
            if (is_array($value)) {
                return $this->buildGraphQLWhereClause($value);
            }

            if (is_string($value)) {
                return $value;
            }
            return '';
        }, $filter['values']);

        // Filter out empty conditions
        $conditions = array_filter($conditions, fn($c) => !empty($c));

        return '{ ' . $logicalOperator . ': [' . implode(', ', $conditions) . '] }';
    }

    private function getGraphQLWhereClause(array $where, array|string|null $whereCondition, &$result = []): array
    {
        if (is_null($whereCondition)) {
            $whereCondition = 'or';
        }
        if (is_string($whereCondition)) {
            if ($whereCondition === 'and' || $whereCondition === 'or') {
                $whereCondition = [
                    $whereCondition . 'Fields' => [
                        'fields' => array_keys($where)
                    ],
                ];
            } else {
                $whereCondition = json_decode(stripslashes($whereCondition), true, 512, JSON_THROW_ON_ERROR);
            }
        }
        if (array_key_exists('fields', $whereCondition)) {
            foreach ($whereCondition['fields'] as $fieldIndex) {
                $result[] = $where[$fieldIndex];
            }
        }
        foreach (['or', 'and'] as $c) {
            if (array_key_exists($c . 'Fields', $whereCondition)) {
                $values = [];
                $result[] = ['condition' => $c, 'values' => &$values];
                $this->getGraphQLWhereClause($where, $whereCondition[$c . 'Fields'], $values);
            }
        }
        return $result;
    }

    private function addKeysToCollection(array &$items, array $selectionSets, ?string $arrayKey = null): void
    {
        $result = [];
        foreach ($items as $item) {
            foreach ($selectionSets as $prop => $selectionSet) {
                if ($selectionSet instanceof ArgumentSelectionSetDto) {
                    $this->_addKeysToCollection($item, $prop, $selectionSet->getKey());
                }
            }
            if (!empty($arrayKey)) {
                $result[$item->{$arrayKey}] = $item;
            } else {
                $result[] = $item;
            }
        }
        $items = $result;
    }

    private function _addKeysToCollection(stdClass $object, string $prop, string $key): void
    {
        $collection = [];
        foreach ($object->{$prop} as $value) {
            $collection[$value->{$key}] = $value;
        }
        $object->{$prop} = $collection;
    }

    public function getPExpeditions(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->pExpeditions) && $getFromSage !== true) {
            return $this->pExpeditions;
        }
        $entityName = SageEntityMenu::PEXPEDITION_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [
                "eIntitule"
            ],
            "filter_type" => [
                "neq"
            ],
            "filter_value" => [
                ''
            ],
            "sort" => '{"cbIndice": "asc"}',
            "paged" => "1",
            "per_page" => "50"
        ];
        $selectionSets = $this->_getPExpeditionSelectionSet();
        $pExpeditions = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
        );
        if (is_array($pExpeditions)) {
            foreach ($pExpeditions as $pExpedition) {
                // necessary for filter `woocommerce_shipping_methods`
                $pExpedition->slug = FDocenteteUtils::slugifyPExpeditionEIntitule($pExpedition->eIntitule);
            }
        }
        $this->pExpeditions = $pExpeditions;
        return $this->pExpeditions;
    }

    public function _getPExpeditionSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'cbIndice',
                'eTypeFrais', // Base de calcul (Montant forfaitaire, quantité DocumentFraisType) // Type des frais d'expédition
                'eTypeCalcul', // Valeur, Grille frais fixe, grille frais variable)
                'eValFrais', // valeur quand eTypeCalcul == 'Valeur'
                'eTypeLigneFrais', // indique si le prix est en HT ou TTC (HT == 0)
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'eIntitule',
            ]),
            'arRefNavigation' => $this->_getFArticleSelectionSet(),
            'fExpeditiongrilles' => $this->_getFExpeditiongrilles(),
        ];
    }

    public function _getFArticleSelectionSet(bool $checkIfExists = false): array
    {
        if ($checkIfExists) {
            return [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                    'arRef',
                ]),
            ];
        }
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'arType',
                'arPoidsNet',
                'arPoidsBrut',
                'arNomencl', // enum
                'arSuiviStock', // enum
                'arCondition', // enum U. Vente
                'arPrixTtc',
                'arUniteVen', // Unité de vente
                'canEditArSuiviStock',
                'clNo1',
                'clNo2',
                'clNo3',
                'clNo4',
                'arSommeil',
                'arEscompte',
                'arVteDebit',
                'arSommeil',
                'arContremarque',
                'arFactPoids',
                'arPublie',
                'arHorsStat',
                'arNotImp',
                'arFactForfait',
                'arUnitePoids', // enum UnitePoidsType 0 = tonne, 1 = quintal, 2 = kilogramme, 3 = gramme, 4 =  milligrame
                'arPoidsNet',
                'arPoidsBrut',
                'arCodeBarre',
            ]),
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                'arPrixAch',
                'arCoef',
                'arPrixVen',
                'arPunet', // dernier prix d'achat
                'arCoutStd',
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'arRef',
                'arDesign',
                'faCodeFamille',
                'arCodeFiscal',
                'arEdiCode',
                'arPays',
                'arRaccourci',
                'arLangue1',
                'arLangue2',
            ]),
            'fArtclients' => new ArgumentSelectionSetDto($this->_getFArtclientsSelectionSet(), 'acCategorie', [
                'where' => new RawObject('{ ctNum: { eq: null } }'),
            ]),
            'fArtfournisses' => new ArgumentSelectionSetDto($this->_getFArtfournisseSelectionSet(), 'ctNum'),
            'fArtglosses' => new ArgumentSelectionSetDto($this->_getFArtglossesSelectionSet(), 'glNo'),
            'fArtstocks' => new ArgumentSelectionSetDto($this->_getFArtstocksSelectionSet(), 'deNo'),
            'prices' => [
                ...$this->_getPriceSelectionSet(),
                'nCatTarif' => [
                    ...$this->_getNCatTarifSelectionSet(),
                ],
                'nCatCompta' => [
                    ...$this->_getNCatComptaSelectionSet(),
                ],
            ],
        ];
    }

    public function _getFArtclientsSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'acCategorie',
                'acPrixVen',
                'acCoef',
                'acPrixTtc',
                'acRemise',
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'ctNum',
            ]),
        ];
    }

    public function _getFArtfournisseSelectionSet()
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'afRefFourniss',
                'afPrincipal',
                'afPrixAch',
                'ctNum'
            ]),
            'ctNumNavigation' => [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                    'ctNum',
                    'ctIntitule',
                ]),
            ],
        ];
    }

    public function _getFArtglossesSelectionSet()
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'glNo',
            ]),
            'glNoNavigation' => $this->_getFGlossaireSelectionSet()
        ];
    }

    public function _getFGlossaireSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'glNo',
                'glDomaine', // 0 -> Article, 1 => document
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'glIntitule',
                'glText',
            ]),
        ];
    }

    public function _getFArtstocksSelectionSet()
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'deNo',
                'asQteMini',
                'asQteMaxi',
                'asPrincipal',
            ]),
        ];
    }

    public function _getPriceSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                'priceHt',
                'priceTtc',
            ]),
            'taxes' => [
                ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                    'amount',
                ]),
                ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                    'taxeNumber',
                ]),
                'fTaxe' => $this->_getFTaxeSelectionSet(),
            ],
        ];
    }

    public function _getFTaxeSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'taIntitule',
                'taCode',
            ]),
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                'taTaux',
            ]),
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'taTtaux',
                'taNp',
            ]),
        ];
    }

    public function _getNCatTarifSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'cbIndice',
                'ctPrixTtc',
            ]),
        ];
    }

    public function _getNCatComptaSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'cbIndice',
            ]),
        ];
    }

    public function _getFExpeditiongrilles(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'egBorne',
                'egFrais',
            ]),
        ];
    }

    public function getPUnites(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->pUnites) && $getFromSage !== true) {
            return $this->pUnites;
        }

        $entityName = SageEntityMenu::PUNITE_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [
                "uIntitule"
            ],
            "filter_type" => [
                "neq"
            ],
            "filter_value" => [
                ''
            ],
            "sort" => '{"cbIndice": "asc"}',
            "paged" => "1",
            "per_page" => "50"
        ];
        $selectionSets = $this->_getPUniteSelectionSet();
        $this->pUnites = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
        );
        return $this->pUnites;
    }

    public function _getPUniteSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'cbIndice',
                'uIntitule',
            ]),
        ];
    }

    public function getFDepots(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->fDepots) && $getFromSage !== true) {
            return $this->fDepots;
        }

        $entityName = SageEntityMenu::FDEPOT_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [],
            "filter_type" => [],
            "filter_value" => [],
            "sort" => '{"deNo": "asc"}',
            "paged" => "1",
            "per_page" => "50"
        ];
        $selectionSets = $this->_getFDepotSelectionSet();
        $this->fDepots = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
        );
        return $this->fDepots;
    }

    public function _getFDepotSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'deIntitule',
            ]),
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'deNo',
            ]),
        ];
    }

    public function getFFamilles(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->fFamilles) && $getFromSage !== true) {
            return $this->fFamilles;
        }

        $entityName = SageEntityMenu::FFAMILLE_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [
                "faType"
            ],
            "filter_type" => [
                "eq"
            ],
            "filter_value" => [
                // enum FamilleType
                // 0 -> Centralisatrice
                // 1 -> Détail
                // 2 -> Total
                "0"
            ],
            "sort" => '{"faCodeFamille": "asc"}',
            "paged" => "1",
            "per_page" => "100"
        ];
        $selectionSets = $this->_getFFamilleSelectionSet();
        $this->fFamilles = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
            allPages: true,
        );
        return $this->fFamilles;
    }

    public function _getFFamilleSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'faCodeFamille',
                'faIntitule',
            ]),
        ];
    }

    public function getFArticle(
        string $arRef,
        bool   $ignorePingApi = false,
        bool   $checkIfExists = false,
    ): StdClass|null
    {
        $fArticle = $this->searchEntities(
            SageEntityMenu::FARTICLE_ENTITY_NAME,
            [
                "filter_field" => [
                    "arRef"
                ],
                "filter_type" => [
                    "eq"
                ],
                "filter_value" => [
                    $arRef
                ],
                "paged" => "1",
                "per_page" => "1"
            ],
            $this->_getFArticleSelectionSet(checkIfExists: $checkIfExists),
            ignorePingApi: $ignorePingApi,
        );
        if (is_null($fArticle) || $fArticle->data->fArticles->totalCount !== 1) {
            return null;
        }
        return $fArticle->data->fArticles->items[0];
    }

    public function getAvailableArRef(
        ?string $arRef = null,
        ?string $faCodeFamille = null,
    ): string
    {
        $query = (new Query('availableArRef'))
            ->setArguments([
                'arRef' => $arRef,
                'faCodeFamille' => $faCodeFamille,
            ]);
        return $this->runQuery($query)->data->availableArRef;
    }

    public function getFDocentetes(
        string $doPiece,
        ?array $doTypes = null,
        ?int   $doDomaine = null,
        ?int   $doProvenance = null,
        bool   $getError = false,
        bool   $ignorePingApi = false,
        bool   $getWordpressIds = false,
        bool   $getFDoclignes = false,
        bool   $getExpedition = false,
        bool   $addWordpressProductId = false,
        bool   $getUser = false,
        bool   $getLivraison = false,
        bool   $addWordpressUserId = false,
        bool   $getLotSerie = false,
        bool   $extended = false,
        bool   $single = false,
    ): array|stdClass|null|false|string
    {
        if ($extended) {
            $filterField = ["extendedDoPieceDoType"];
            $filterType = ["object"];
            $filterValue = [
                "doPiece" => ["eq" => $doPiece],
            ];
            if (!empty($doTypes)) {
                $filterValue['doType'] = ["in" => $doTypes];
            }
            $filterValue = [$filterValue];
        } else {
            $filterField = ["doPiece"];
            $filterType = ["eq"];
            $filterValue = [$doPiece];
            if (!empty($doTypes)) {
                $filterField[] = "doType";
                $filterType[] = "in";
                $filterValue[] = implode(',', $doTypes);
            }
            if ($doDomaine !== null) {
                $filterField[] = "doDomaine";
                $filterType[] = "eq";
                $filterValue[] = $doDomaine;
            }
            if ($doProvenance !== null) {
                $filterField[] = "doProvenance";
                $filterType[] = "eq";
                $filterValue[] = $doProvenance;
            }
        }
        $fDocentetes = $this->searchEntities(
            SageEntityMenu::FDOCENTETE_ENTITY_NAME,
            [
                "filter_field" => $filterField,
                "filter_type" => $filterType,
                "filter_value" => $filterValue,
                'where_condition' => 'and',
                "paged" => "1",
                "per_page" => $single ? "1" : "20"
            ],
            $this->_getFDocenteteSelectionSet(
                getFDoclignes: $getFDoclignes,
                getExpedition: $getExpedition,
                getUser: $getUser,
                getLivraison: $getLivraison,
                getLotSerie: $getLotSerie,
            ),
            getError: $getError,
            ignorePingApi: $ignorePingApi,
        );
        if (is_null($fDocentetes) || is_string($fDocentetes)) {
            return $fDocentetes;
        }
        if ($fDocentetes->data->fDocentetes->totalCount !== 1 && $single) {
            return false;
        }
        $fDocentetes = $fDocentetes->data->fDocentetes->items;
        if ($addWordpressUserId) {
            $fDocentetes = $this->addWordpressUserId($fDocentetes);
        }
        if ($addWordpressProductId) {
            $fDoclignes = [];
            foreach ($fDocentetes as $fDocentete) {
                $fDoclignes = [...$fDoclignes, ...$fDocentete->fDoclignes];
            }
            $fDoclignes = $this->addWordpressProductId($fDoclignes);
            foreach ($fDocentetes as $fDocentete) {
                $fDocentete->fDoclignes = array_filter($fDoclignes, static function (stdClass $fDocligne) use ($fDocentete) {
                    return $fDocligne->doPiece === $fDocentete->doPiece && $fDocligne->doType === $fDocentete->doType;
                });
            }
        }
        if ($getWordpressIds) {
            $values = array_map(static function (stdClass $fDocentete) {
                return json_encode([
                    'doPiece' => $fDocentete->doPiece,
                    'doType' => $fDocentete->doType,
                ], JSON_THROW_ON_ERROR);
            }, $fDocentetes);
            global $wpdb;
            $r = $wpdb->get_results(
                $wpdb->prepare("
SELECT order_id, meta_value
FROM " . $wpdb->prefix . "wc_orders_meta
WHERE meta_key = %s
  AND meta_value IN ('" . implode(', ', $values) . "')
", [Sage::META_KEY_IDENTIFIER]));
            foreach ($fDocentetes as $i => $fDocentete) {
                $fDocentetes[$i]->wordpressIds = [];
                foreach ($r as $wcOrdersMeta) {
                    $data = json_decode($wcOrdersMeta->meta_value, false, 512, JSON_THROW_ON_ERROR);
                    if ($data->doPiece === $fDocentete->doPiece &&
                        $data->doType === $fDocentete->doType) {
                        $fDocentetes[$i]->wordpressIds[] = (int)$wcOrdersMeta->order_id;
                        break;
                    }
                }
            }
        }

        if ($single) {
            return $fDocentetes[0];
        }
        return $fDocentetes;
    }

    public function _getFDocenteteSelectionSet(
        bool $getFDoclignes = false,
        bool $getExpedition = false,
        bool $getUser = false,
        bool $getLivraison = false,
        bool $getLotSerie = false, // todo
    ): array
    {
        $result = [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", ['doType']),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'doPiece',
                'doTiers',
                'doStatut',
                'doStatutString',
                'doRef',
                'nCatCompta', // catégorie comptable
                'doTarif', // catégorie tarifaire
            ]),
        ];
        if ($getExpedition) {
            $result['doExpeditNavigation'] = $this->_getPExpeditionSelectionSet();
            $result['fraisExpedition'] = $this->_getFraisExpeditionSelectionSet();
        }
        if ($getFDoclignes) {
            $result['fDoclignes'] = new ArgumentSelectionSetDto($this->_getFDocligneSelectionSet(), 'dlNo');
        }
        if ($getUser) {
            $result['doTiersNavigation'] = $this->_getFComptetSelectionSet();
        }
        if ($getLivraison) {
            $result['cbLiNoNavigation'] = $this->_getFLivraisonSelectionSet();
        }
        return $result;
    }

    public function _getFraisExpeditionSelectionSet(): array
    {
        return [
            ...$this->_getPriceSelectionSet(),
        ];
    }

    public function _getFDocligneSelectionSet(
        bool $getLotSerie = false,
    ): array
    {
        $r = [
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                'dlMontantHt',
                // 'dlMontantTtc', // don't use dlMontantTtc because it applies ignored taxe
            ]),
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                ...array_map(static function (string $field) {
                    return 'dlCodeTaxe' . $field;
                }, FDocenteteUtils::ALL_TAXES),
                ...array_map(static function (string $field) {
                    return 'dlMontantTaxe' . $field;
                }, FDocenteteUtils::ALL_TAXES),
            ]),
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'dlNo',
                'doType',
                'dlQte',
                ...array_map(static function (string $field) {
                    return 'dlQte' . $field;
                }, FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE),
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'doPiece',
                'arRef',
                'dlDesign',
                ...array_map(static function (string $field) {
                    return 'dlPiece' . $field;
                }, FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE),
            ]),
        ];
        if ($getLotSerie) {
            $r['fLotseriesNavigation'] = [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                    'lsNoSerie',
                ]),
            ];
        }
        return $r;
    }

    private function addWordpressUserId(array $fDocentetes): array
    {
        global $wpdb;
        $ctNums = array_map(static function (stdClass $fDocentete) {
            return $fDocentete->doTiers;
        }, $fDocentetes);
        $r = $wpdb->get_results(
            $wpdb->prepare("
SELECT user_id, meta_value
FROM {$wpdb->usermeta}
WHERE meta_key = %s
  AND meta_value IN ('" . implode(', ', $ctNums) . "')
", [Sage::META_KEY_CT_NUM]));
        $mapping = [];
        foreach ($r as $row) {
            $mapping[$row->meta_value] = $row->user_id;
        }
        foreach ($fDocentetes as $fDocentete) {
            $fDocentete->userId = null;
            if (array_key_exists($fDocentete->doTiers, $mapping)) {
                $fDocentete->userId = (int)$mapping[$fDocentete->doTiers];
            }
        }

        return $fDocentetes;
    }

    private function addWordpressProductId(array $fDoclignes): array
    {
        global $wpdb;
        $arRefs = array_values(array_unique(array_map(static function (stdClass $fDocligne) {
            return $fDocligne->arRef;
        }, $fDoclignes)));
        $r = $wpdb->get_results(
            $wpdb->prepare(
                "
SELECT post_id, meta_value
FROM {$wpdb->postmeta}
         INNER JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->posts}.post_status != 'trash'
WHERE {$wpdb->postmeta}.meta_key = %s
  AND {$wpdb->postmeta}.meta_value IN ('" . implode("','", $arRefs) . "')
", [
                Sage::META_KEY_AR_REF,
            ]));
        foreach ($fDoclignes as $fDocligne) {
            $fDocligne->postId = null;
            foreach ($r as $product) {
                if ($fDocligne->arRef === $product->meta_value) {
                    $fDocligne->postId = (int)$product->post_id;
                    break;
                }
            }
        }
        return $fDoclignes;
    }

    public function getFComptet(string $ctNum, bool $ignorePingApi = false): StdClass|null
    {
        $fComptet = $this->searchEntities(
            SageEntityMenu::FCOMPTET_ENTITY_NAME,
            [
                "filter_field" => [
                    "ctNum"
                ],
                "filter_type" => [
                    "eq"
                ],
                "filter_value" => [
                    $ctNum
                ],
                "paged" => "1",
                "per_page" => "1"
            ],
            $this->_getFComptetSelectionSet(),
            ignorePingApi: $ignorePingApi,
        );
        if (is_null($fComptet) || $fComptet->data->fComptets->totalCount !== 1) {
            return null;
        }

        return $fComptet->data->fComptets->items[0];
    }

    public function getPCattarifs(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->pCattarifs) && $getFromSage !== true) {
            return $this->pCattarifs;
        }
        $entityName = SageEntityMenu::PCATTARIF_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [
                "ctIntitule"
            ],
            "filter_type" => [
                "neq"
            ],
            "filter_value" => [
                ''
            ],
            "sort" => '{"cbIndice": "asc"}',
            "paged" => "1",
            "per_page" => "100"
        ];
        $selectionSets = $this->_getPCattarifSelectionSet();
        $this->pCattarifs = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
            arrayKey: 'cbIndice',
        );
        return $this->pCattarifs;
    }

    public function _getPCattarifSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'cbIndice',
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'ctIntitule',
            ]),
        ];
    }

    public function getFGlossaires(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->fGlossaires) && $getFromSage !== true) {
            return $this->fGlossaires;
        }
        $entityName = SageEntityMenu::FGLOSSAIRE_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [],
            "filter_type" => [],
            "filter_value" => [],
            "sort" => '{"glNo": "asc"}',
            "paged" => "1",
            "per_page" => "100"
        ];
        $selectionSets = $this->_getFGlossaireSelectionSet();
        $this->fGlossaires = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
            allPages: true,
        );
        return $this->fGlossaires;
    }

    public function getFCatalogues(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->fCatalogues) && $getFromSage !== true) {
            return $this->fCatalogues;
        }
        $entityName = SageEntityMenu::FCATALOGUE_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [],
            "filter_type" => [],
            "filter_value" => [],
            "sort" => '{"clNo": "asc"}',
            "paged" => "1",
            "per_page" => "100"
        ];
        $selectionSets = $this->_getFCatalogueSelectionSet();
        $this->fCatalogues = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
            allPages: true,
        );
        return $this->fCatalogues;
    }

    public function _getFCatalogueSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'clNo',
                'clNoParent',
                'clNiveau',
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'clIntitule',
            ]),
        ];
    }

    public function getCbSysLibres(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->cbSysLibres) && $getFromSage !== true) {
            return $this->cbSysLibres;
        }
        $entityName = SageEntityMenu::CBSYSLIBRE_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [],
            "filter_type" => [],
            "filter_value" => [],
            "sort" => '{"cbPos": "asc"}',
            "paged" => "1",
            "per_page" => "100"
        ];
        $selectionSets = $this->_getCbSysLibreSelectionSet();
        $this->cbSysLibres = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
            allPages: true,
        );
        return $this->cbSysLibres;
    }

    public function _getCbSysLibreSelectionSet()
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'cbFile',
                'cbName',
            ]),
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'cbLen',
                'cbType',
            ]),
        ];
    }

    public function getFPays(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->fPays) && $getFromSage !== true) {
            return $this->fPays;
        }
        $entityName = SageEntityMenu::FPAYS_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "paged" => "1",
            "per_page" => "300" // 197 countries exists
        ];
        $selectionSets = $this->_getFPaySelectionSet();
        $this->fPays = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi
        );
        return $this->fPays;
    }

    public function _getFPaySelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'paIntitule',
                'paCode',
            ]),
        ];
    }

    public function getFTaxes(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->fTaxes) && $getFromSage !== true) {
            return $this->fTaxes;
        }
        $entityName = SageEntityMenu::FTAXES_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "paged" => "1",
            "per_page" => "100",
        ];
        $selectionSets = $this->_getFTaxeSelectionSet();
        $this->fTaxes = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
            allPages: true,
        );
        return $this->fTaxes;
    }

    public function getPCatComptas(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->pCatComptas) && $getFromSage !== true) {
            return $this->pCatComptas;
        }
        $entityName = SageEntityMenu::PCATCOMPTA_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [],
            "filter_type" => [],
            "filter_value" => [],
            "sort" => '{"cbMarq": "asc"}',
            "paged" => "1",
            "per_page" => "1"
        ];
        $selectionSets = $this->_getPCatComptaSelectionSet();
        $pCatComptas = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi
        );
        if (!is_null($pCatComptas) && !is_string($pCatComptas)) {
            $result = [];
            $pos = 3;
            foreach ($pCatComptas[0] as $key => $pCatCompta) {
                if ($pCatCompta === '') {
                    continue;
                }
                [$tiers, $i] = preg_split('/(?<=.{' . $pos . '})/', str_replace('caCompta', '', $key), 2);
                $stdClass = new stdClass();
                $stdClass->label = $pCatCompta;
                $stdClass->cbIndice = (int)$i;
                $result[$tiers][(int)$i] = $stdClass;
            }
        } else {
            $result = $pCatComptas;
        }
        $this->pCatComptas = $result;
        return $this->pCatComptas;
    }

    public function _getPCatComptaSelectionSet(): array
    {
        $result = [];
        foreach (PCatComptaUtils::ALL_TIERS_TYPE as $t) {
            $result = [
                ...$result,
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                    ...array_map(static function (int $number) use ($t) {
                        return 'caCompta' . $t . str_pad((string)$number, 2, '0', STR_PAD_LEFT);
                    }, range(1, PCatComptaUtils::NB_TIERS_TYPE)),
                ]),
            ];
        }
        return $result;
    }

    public function updateFComptetFromWebsite(
        string $ctNum,
        bool   $getError = false,
    ): StdClass|null|string
    {
        $arguments = [
            'ctNum' => $ctNum,
            'websiteId' => (int)get_option(Sage::TOKEN . '_website_id'),
        ];
        $mutation = (new Mutation('updateFComptetFromWebsite'))
            ->setVariables([new Variable('updateFComptetFromWebsiteDto', 'UpdateFComptetFromWebsiteDtoInput', true)])
            ->setArguments(['updateFComptetFromWebsiteDto' => '$updateFComptetFromWebsiteDto'])
            ->setSelectionSet($this->formatSelectionSet($this->_getFComptetSelectionSet()));
        $variables = ['updateFComptetFromWebsiteDto' => $arguments];
        $result = $this->runQuery($mutation, $getError, $variables);

        if (!is_null($result) && !is_string($result)) {
            return $result->data->updateFComptetFromWebsite;
        }
        return $result;
    }

    public function updateAllSageEntitiesInOption(array $ignores = []): void
    {
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            if (in_array($methodName, $ignores, true)) {
                continue;
            }
            // Check if the method name starts with "get"
            if (str_starts_with($methodName, 'get')) {
                $parameters = $method->getParameters();
                $paramNames = array_map(fn($param) => $param->getName(), $parameters);

                // Check if both 'useCache' and 'getFromSage' are in the parameter list
                if (
                    in_array('useCache', $paramNames, true) &&
                    in_array('getFromSage', $paramNames, true)
                ) {
                    // Build argument list in correct order with values (example: true, false)
                    $args = [];

                    foreach ($parameters as $param) {
                        if ($param->getName() === 'useCache') {
                            $args[] = true;
                        } elseif ($param->getName() === 'getFromSage') {
                            $args[] = true;
                        } else {
                            // Provide default or null for other parameters
                            $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                        }
                    }

                    // Call the method with constructed arguments
                    $method->invokeArgs($this->sage->sageGraphQl, $args);
                }
            }
        }
    }

    public function updateFArticleFromWebsite(
        string $arRef,
        bool   $new,
        bool   $getError = false,
    ): StdClass|null|string
    {
        $arguments = [
            'arRef' => $arRef,
            'new' => $new,
            'websiteId' => (int)get_option(Sage::TOKEN . '_website_id'),
        ];
        $mutation = (new Mutation('updateFArticleFromWebsite'))
            ->setVariables([new Variable('updateFArticleFromWebsiteDto', 'UpdateFArticleFromWebsiteDtoInput', true)])
            ->setArguments(['updateFArticleFromWebsiteDto' => '$updateFArticleFromWebsiteDto'])
            ->setSelectionSet($this->formatSelectionSet($this->_getFArticleSelectionSet()));
        $variables = ['updateFArticleFromWebsiteDto' => $arguments];
        $result = $this->runQuery($mutation, $getError, $variables);

        if (!is_null($result) && !is_string($result)) {
            return $result->data->updateFArticleFromWebsite;
        }
        return $result;
    }

    public function getPPreference(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): stdClass|null|string
    {
        if (!is_null($this->pPreference) && $getFromSage !== true) {
            return $this->pPreference;
        }
        $entityName = SageEntityMenu::PPREFERENCE_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "paged" => "1",
            "per_page" => "1",
            "sort" => '{"cbMarq": "asc"}',
        ];
        $selectionSets = $this->_getPPreferenceSelectionSet();
        $pPreference = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi,
        );
        if (is_array($pPreference) && count($pPreference) === 1) {
            $pPreference = $pPreference[0];
        }
        $this->pPreference = $pPreference;
        return $this->pPreference;
    }

    public function _getPPreferenceSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'prUnitePoids',
            ]),
        ];
    }

    public function getSageEntityMenuWithQuery(SageEntityMenu $sageEntityMenu, bool $getData = true): array
    {
        $queryParams = $_GET;
        $entityName = $sageEntityMenu->getEntityName();
        $rawShowFields = get_option(Sage::TOKEN . '_' . $entityName . '_show_fields');
        $rawFilterFields = get_option(Sage::TOKEN . '_' . $entityName . '_filter_fields');
        $perPage = get_option(Sage::TOKEN . '_' . $entityName . '_perPage');
        if ($rawShowFields === false) {
            $rawShowFields = $sageEntityMenu->getDefaultFields();
        }
        if ($rawFilterFields === false) {
            $rawFilterFields = $sageEntityMenu->getDefaultFields();
        }

        $mandatoryFields = $sageEntityMenu->getMandatoryFields();
        $hideFields = [...array_diff($mandatoryFields, $rawShowFields)];
        $rawShowFields = array_unique([...$rawShowFields, ...$hideFields]);
        $showFields = [];
        $filterFields = [];
        $inputFields = $this->getTypeFilter($sageEntityMenu->getFilterType()) ?? [];
        $transDomain = $sageEntityMenu->getTransDomain();
        $trans = SageTranslationUtils::getTranslations();
        $selectionSets = [];
        foreach ($sageEntityMenu->getSelectionSet() as $selectionSet) {
            if (is_array($selectionSet) && array_key_exists('name', $selectionSet)) {
                $selectionSets[$selectionSet['name']] = $selectionSet['type'];
            }
        }
        foreach ([
                     [
                         'rawFields' => array_unique([...$rawShowFields, ...$mandatoryFields]),
                         'array' => &$showFields,
                     ],
                     [
                         'rawFields' => $rawFilterFields,
                         'array' => &$filterFields,
                     ]
                 ] as $fieldType) {
            foreach ($fieldType['rawFields'] as $rawField) {
                $f = [
                    'name' => $rawField,
                    'type' => $selectionSets[$rawField] ?? 'StringOperationFilterInput',
                    'transDomain' => $transDomain,
                    'values' => null,
                ];
                if (array_key_exists($rawField, $inputFields)) {
                    $f['name'] = $inputFields[$rawField]->name;
                    $f['type'] = $inputFields[$rawField]->type->name;
                }
                $v = $trans[$entityName][$rawField];
                if (is_array($v) && array_key_exists('values', $v)) {
                    $f['values'] = $v['values'];
                }
                $fieldType['array'][] = $f;
            }
        }

        if (!isset($queryParams['per_page'])) {
            $queryParams['per_page'] = get_option(Sage::TOKEN . '_' . $entityName . '_perPage');
            if ($queryParams['per_page'] === false) {
                $queryParams['per_page'] = (string)SageSettings::$defaultPagination;
            }
        }

        $data = [];
        if ($getData) {
            $data = json_decode(json_encode($this->searchEntities($entityName, $queryParams, $showFields, ignorePingApi: true)
                , JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $data = $this->sage->sageWoocommerce->populateMetaDatas($data, $showFields, $sageEntityMenu);
        }
        $hideFields = array_map(static function (string $hideField) {
            return str_replace(SageSettings::PREFIX_META_DATA, '', $hideField);
        }, $hideFields);
        return [
            $data,
            $showFields,
            $filterFields,
            $hideFields,
            $perPage,
            $queryParams,
        ];
    }

    public function getTypeFilter(string $object): array|null
    {
        $cacheName = 'TypeFilter_' . $object;
        if (!$this->pingApi) {
            $result = $this->sage->cache->get($cacheName, static fn() => null);
            if (is_null($result)) {
                $this->sage->cache->delete($cacheName);
            }
            return $result;
        }

        $sageGraphQl = $this;
        $function = static function () use ($object, $sageGraphQl) {
            $query = (new Query('__type'))
                ->setArguments(['name' => $object])
                ->setSelectionSet(
                    [
                        'name',
                        (new Query('inputFields'))
                            ->setSelectionSet(
                                [
                                    'name',
                                    (new Query('type'))
                                        ->setSelectionSet(
                                            [
                                                'name',
                                            ]
                                        ),
                                ],
                            ),
                    ]
                );
            $temps = $sageGraphQl->runQuery($query)?->data?->__type?->inputFields;
            $r = [];
            foreach ($temps as $temp) {
                $r[$temp->name] = $temp;
            }
            return $r;
        };
        $typeModel = $this->sage->cache->get($cacheName, $function);
        if (empty($typeModel)) {
            $this->sage->cache->delete($cacheName);
            $typeModel = $this->sage->cache->get($cacheName, $function);
        }

        return $typeModel;
    }

    private function ping(): void
    {
        $hostUrl = get_option(Sage::TOKEN . '_api_host_url');
        $message = null;
        if (!is_string($hostUrl) || ($hostUrl === '' || $hostUrl === '0')) {
            $message = __("Veuillez renseigner l'host du serveur Sage.", Sage::TOKEN);
        } else if (filter_var($hostUrl, FILTER_VALIDATE_URL) === false) {
            $message = __("L'host du serveur Sage n'est pas une url valide.", Sage::TOKEN);
        }
        if (!is_null($message)) {
            add_action('admin_notices', static function () use ($message): void {
                ?>
                <div class="notice notice-info">
                    <p>
                        <?= $message ?>
                    </p>
                </div>
                <?php
            });
            $this->pingApi = false;
            return;
        }

        $curlHandle = curl_init();
        $sslVerification = (bool)get_option(Sage::TOKEN . '_activate_https_verification_graphql');
        $data = [
            CURLOPT_URL => $hostUrl . '/healthz',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        if (!$sslVerification) {
            $data[CURLOPT_SSL_VERIFYPEER] = false;
            $data[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($curlHandle, $data);
        $response = curl_exec($curlHandle);
        $errorMsg = null;
        if (curl_errno($curlHandle) !== 0) {
            $errorMsg = curl_error($curlHandle);
        }

        curl_close($curlHandle);
        $this->pingApi = $response === 'Healthy';
        if (!$this->pingApi) {
            add_action('admin_notices', static function () use ($errorMsg): void {
                ?>
                <div id="<?= Sage::TOKEN ?>_join_api" class="error"><p>
                        <?= __("L'API Sage n'est pas joignable. Avez vous lancé le serveur ?", Sage::TOKEN) ?>
                        <?php
                        if (!is_null($errorMsg)) {
                            echo "<br>" . __('Error', Sage::TOKEN) . ": " . $errorMsg;
                        }
                        ?>
                    </p></div>
                <?php
            });
        }
    }
}
