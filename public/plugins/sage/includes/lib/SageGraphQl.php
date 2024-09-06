<?php

namespace App\lib;

use App\class\SageEntityMenu;
use App\enum\WebsiteEnum;
use App\Sage;
use App\SageSettings;
use App\Utils\FDocenteteUtils;
use App\Utils\PCatComptaUtils;
use Exception;
use GraphQL\Client;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\RawObject;
use StdClass;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

// https://github.com/mghoneimy/php-graphql-client
final class SageGraphQl
{
    private static ?self $_instance = null;

    private ?Client $client = null;

    private bool $pingApi = false;

    private ?array $pExpeditions = null;

    private ?array $pCatComptas = null;

    private ?array $pCattarifs = null;

    private ?array $fPays = null;

    private ?array $fTaxes = null;

    private ?stdClass $pDossier = null;

    private function __construct(public ?Sage $sage)
    {
        if (is_admin()) {
            $this->ping();
        }
    }

    private function ping(): void
    {
        $hostUrl = get_option(Sage::TOKEN . '_api_host_url');
        if (!is_string($hostUrl) || ($hostUrl === '' || $hostUrl === '0')) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="notice notice-info">
                    <p>
                        <?= __("Veuillez renseigner l'host du serveur Sage.", 'sage') ?>
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
                <div class="error"><p>
                        <?= __('Sage API is not reachable. Did you launched the server ?', 'sage') ?>
                        <?php
                        if (!is_null($errorMsg)) {
                            echo "<br>" . __('Error', 'sage') . ": " . $errorMsg;
                        }
                        ?>
                    </p></div>
                <?php
            });
        }
    }

    public static function instance(Sage $sage): ?self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($sage);
        }

        return self::$_instance;
    }

    public function createUpdateWebsite(
        string      $name,
        string      $username,
        string      $password,
        WebsiteEnum $websiteEnum,
        string      $host,
        string      $protocol,
        bool        $forceSsl,
        string      $dbHost,
        string      $tablePrefix,
        string      $dbName,
        string      $dbUsername,
        string      $dbPassword,
        bool        $syncArticlesToWebsite,
    ): StdClass|null
    {
        $query = (new Mutation('createUpdateWebsite'))
            ->setArguments([
                'name' => new RawObject('"' . $name . '"'),
                'username' => new RawObject('"' . $username . '"'),
                'password' => new RawObject('"' . $password . '"'),
                'type' => new RawObject(strtoupper($websiteEnum->name)),
                'host' => new RawObject('"' . $host . '"'),
                'protocol' => new RawObject('"' . $protocol . '"'),
                'forceSsl' => new RawObject($forceSsl ? 'true' : 'false'),
                'dbHost' => new RawObject('"' . $dbHost . '"'),
                'dbUsername' => new RawObject('"' . $dbUsername . '"'),
                'dbPassword' => new RawObject('"' . $dbPassword . '"'),
                'tablePrefix' => new RawObject('"' . $tablePrefix . '"'),
                'dbName' => new RawObject('"' . $dbName . '"'),
                'syncArticlesToWebsite' => new RawObject($syncArticlesToWebsite ? 'true' : 'false'),
            ])
            ->setSelectionSet(
                [
                    'id',
                ]
            );
        $r = $this->runQuery($query);
        if (is_string($r)) {
            return null;
        }
        return $r;
    }

    private function runQuery(Query|Mutation $gql, bool $getError = false): array|object|null|string
    {
        $client = $this->getClient();
        try {
            return $client->runQuery($gql)?->getResults();
        } catch (Throwable $throwable) {
            // todo store logs
            $message = $throwable->getMessage();
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

    public function createFComptet(
        string  $ctIntitule,
        string  $ctEmail,
        ?string $ctNum = null,
        ?bool   $autoGenerateCtNum = null,
    ): StdClass|null
    {
        $arguments = [
            'ctIntitule' => new RawObject('"' . $ctIntitule . '"'),
            'ctEmail' => new RawObject('"' . $ctEmail . '"'),
        ];
        if (!is_null($ctNum)) {
            $arguments['ctNum'] = new RawObject('"' . $ctNum . '"');
        }
        if (!is_null($autoGenerateCtNum)) {
            $arguments['autoGenerateCtNum'] = new RawObject($autoGenerateCtNum ? 'true' : 'false');
        }
        $query = (new Mutation('createFComptet'))
            ->setArguments($arguments)
            ->setSelectionSet($this->formatSelectionSet($this->_getFComptetSelectionSet()));
        $result = $this->runQuery($query);
        if (!is_null($result) && !is_string($result)) {
            return $result->data->createFComptet;
        }
        return null;
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
                $result[] = (new Query($key))->setSelectionSet($this->formatSelectionSet($value));
            }
        }
        return $result;
    }

    private function _getFComptetSelectionSet(): array
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
            ]),
            'fLivraisons' => $this->_getFLivraisonSelectionSet(),
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

    private function _getFLivraisonSelectionSet(): array
    {
        return [
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
            $this->sage->cache->delete($cacheName);
            return null;
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

    public function getTypeFilter(string $object): array|null
    {
        $cacheName = 'TypeFilter_' . $object;
        if (!$this->pingApi) {
            $this->sage->cache->delete($cacheName);
            return null;
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

    public function getPDossier(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): stdClass|null|string
    {
        if (!is_null($this->pDossier)) {
            return $this->pDossier;
        }
        $entityName = SageEntityMenu::PDOSSIER_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "paged" => "1",
            "per_page" => "1"
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

    private function _getPDossierSelectionSet(): array
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
                $entities = json_decode($entities, false, 512, JSON_THROW_ON_ERROR);
            }
            $tryGetOption = true;
        }
        if (is_null($entities)) {
            $entities = $this->searchEntities(
                $entityName,
                $queryParams,
                $selectionSets,
                $cacheName,
                $getError,
                $ignorePingApi
            );
            if (is_null($entities) || is_string($entities)) {
                if (!$tryGetOption) {
                    $entitiesBdd = get_option($optionName, null);
                    if ($entitiesBdd !== 'null' && $entitiesBdd !== null) {
                        $entities = json_decode($entitiesBdd, false, 512, JSON_THROW_ON_ERROR);
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
    ): StdClass|null|string
    {
        if (!is_null($cacheName)) {
            $cacheName = 'SearchEntities_' . $cacheName;
        }
        if (!$this->pingApi && !$ignorePingApi) {
            if (!is_null($cacheName)) {
                $this->sage->cache->delete($cacheName);
            }
            return null;
        }

        $sageGraphQl = $this;
        $function = static function () use ($entityName, $queryParams, $selectionSets, $getError, $sageGraphQl) {
            $nbPerPage = (int)($queryParams["per_page"] ?? SageSettings::$defaultPagination);
            $page = (int)($queryParams["paged"] ?? 1);
            $where = [];
            if (array_key_exists('filter_field', $queryParams)) {
                $primaryFields = array_filter($selectionSets, static fn(array $field): bool => array_key_exists('name', $field));
                foreach ($queryParams["filter_field"] as $k => $v) {
                    $fieldType = current(array_filter($primaryFields, static fn(array $field): bool => $field['name'] === $v))['type'];
                    if (in_array($fieldType, [
                        'StringOperationFilterInput',
                        'DateTimeOperationFilterInput',
                        'UuidOperationFilterInput',
                    ])) {
                        $queryParams["filter_value"][$k] = '"' . $queryParams["filter_value"][$k] . '"';
                    }

                    if (!isset($where[$queryParams["filter_field"][$k]])) {
                        $where[$queryParams["filter_field"][$k]] = [];
                    }

                    $v = $queryParams["filter_value"][$k];
                    if (in_array($queryParams["filter_type"][$k], ['in', 'nin'])) {
                        if (str_starts_with($v, '"') && str_ends_with($v, '"')) {
                            $v = str_replace(',', '","', $v);
                        }
                        $v = '[' . $v . ']';
                    }
                    $where[$queryParams["filter_field"][$k]][] = $queryParams["filter_type"][$k] . ': ' . $v;
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
                $stringWhere = [];
                foreach ($where as $f => $w) {
                    $stringWhere[] = $f . ': { ' . implode(',', $w) . ' }';
                }

                $arguments['where'] = new RawObject('{' . ($queryParams["where_condition"] ?? 'or') . ': [{' . implode('},{', $stringWhere) . '}]}');
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
            return $function();
        }
        $results = $this->sage->cache->get($cacheName, $function);
        if (empty($results) || is_string($results)) { // if $results is string it means it's an error
            $this->sage->cache->delete($cacheName);
            $results = $this->sage->cache->get($cacheName, $function);
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

    public function getPExpeditions(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->pExpeditions)) {
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
                ""
            ],
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
            $ignorePingApi
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

    private function _getPExpeditionSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", ['cbMarq']),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", ['eIntitule']),
            'arRefNavigation' => $this->_getFArticleSelectionSet(),
        ];
    }

    private function _getFArticleSelectionSet(bool $forExpedition = false): array
    {
        if ($forExpedition) {
            return [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", ['arRef']),
            ];
        }
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", ['arRef', 'arDesign']),
            'prices' => [
                ...$this->_getPriceSelectionSet(),
                ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                    'nCatTarif',
                    'nCatCompta'
                ]),
            ],
        ];
    }

    private function _getPriceSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                'priceHt',
                'priceTtc',
            ]),
            'taxes' => [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                    'taIntitule',
                    'taCode',
                ]),
                ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                    'amount'
                ]),
                ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                    'taxeNumber'
                ]),
            ],
        ];
    }

    public function getFArticle(string $arRef, bool $ignorePingApi = false): StdClass|null
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
            $this->_getFArticleSelectionSet(),
            ignorePingApi: $ignorePingApi,
        );
        if (is_null($fArticle) || $fArticle->data->fArticles->totalCount !== 1) {
            return null;
        }

        return $fArticle->data->fArticles->items[0];
    }

    public function getFDocentetes(
        string $doPiece,
        bool   $getError = false,
        bool   $ignorePingApi = false,
    ): array|null|string
    {
        $fDocentetes = $this->searchEntities(
            SageEntityMenu::FDOCENTETE_ENTITY_NAME,
            [
                "filter_field" => [
                    "doPiece"
                ],
                "filter_type" => [
                    "eq"
                ],
                "filter_value" => [
                    $doPiece
                ],
                "paged" => "1",
                "per_page" => "10"
            ],
            $this->_getFDocenteteSelectionSet(),
            getError: $getError,
            ignorePingApi: $ignorePingApi,
        );
        if (is_null($fDocentetes) || is_string($fDocentetes)) {
            return $fDocentetes;
        }

        return $fDocentetes->data->fDocentetes->items;
    }

    private function _getFDocenteteSelectionSet(
        bool $getFDoclignes = false,
        bool $getExpedition = false,
        bool $getUser = false,
        bool $getLivraison = false,
    ): array
    {
        $result = [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", ['doType']),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'doPiece',
                'doTiers',
            ]),
        ];
        if ($getExpedition) {
            $result['doExpeditNavigation'] = $this->_getPExpeditionSelectionSet();
            $result['fraisExpedition'] = $this->_getFraisExpeditionSelectionSet();
        }
        if ($getFDoclignes) {
            $result['fDoclignes'] = $this->_getFDocligneSelectionSet();
        }
        if ($getUser) {
            $result['doTiersNavigation'] = $this->_getFComptetSelectionSet();
        }
        if ($getLivraison) {
            $result['cbLiNoNavigation'] = $this->_getFLivraisonSelectionSet();
        }
        return $result;
    }

    private function _getFraisExpeditionSelectionSet(): array
    {
        return [
            ...$this->_getPriceSelectionSet(),
        ];
    }

    private function _getFDocligneSelectionSet(): array
    {
        return [
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
    }

    public function getFDocentete(
        string $doPiece,
        int    $doType,
        bool   $getError = false,
        bool   $getFDoclignes = false,
        bool   $getExpedition = false,
        bool   $ignorePingApi = false,
        bool   $addWordpressProductId = false,
        bool   $getUser = false,
        bool   $getLivraison = false,
    ): stdClass|null|false|string
    {
        if (!$this->pingApi && !$ignorePingApi) {
            return null;
        }
        $fDocentetes = $this->searchEntities(
            SageEntityMenu::FDOCENTETE_ENTITY_NAME,
            [
                "filter_field" => [
                    "doPiece",
                    "doType",
                ],
                "filter_type" => [
                    "eq",
                    "eq",
                ],
                "filter_value" => [
                    $doPiece,
                    $doType,
                ],
                'where_condition' => 'and',
                "paged" => "1",
                "per_page" => "1"
            ],
            $this->_getFDocenteteSelectionSet(
                getFDoclignes: $getFDoclignes,
                getExpedition: $getExpedition,
                getUser: $getUser,
                getLivraison: $getLivraison,
            ),
            getError: $getError,
            ignorePingApi: $ignorePingApi,
        );
        if (
            is_null($fDocentetes) ||
            is_string($fDocentetes)
        ) {
            return $fDocentetes;
        }
        if ($fDocentetes->data->fDocentetes->totalCount !== 1) {
            return false;
        }
        $result = $fDocentetes->data->fDocentetes->items[0];
        if ($addWordpressProductId) {
            $result->fDoclignes = $this->addWordpressProductId($result->fDoclignes);
        }

        return $result;
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
        if (!is_null($this->pCattarifs)) {
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
            $ignorePingApi
        );
        return $this->pCattarifs;
    }

    private function _getPCattarifSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'cbMarq',
                'cbIndice',
            ]),
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'ctIntitule',
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
        if (!is_null($this->fPays)) {
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

    private function _getFPaySelectionSet(): array
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
        if (!is_null($this->fTaxes)) {
            return $this->fTaxes;
        }
        $entityName = SageEntityMenu::FTAXES_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "paged" => "1",
            "per_page" => "1000",
        ];
        $selectionSets = $this->_getFTaxeSelectionSet();
        $this->fTaxes = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi
        );
        return $this->fTaxes;
    }

    private function _getFTaxeSelectionSet(): array
    {
        return [
            ...$this->_formatOperationFilterInput("StringOperationFilterInput", [
                'taCode',
            ]),
            ...$this->_formatOperationFilterInput("DecimalOperationFilterInput", [
                'taTaux',
                'taNp',
            ]),
        ];
    }

    public function getPCatComptas(
        bool  $useCache = true,
        ?bool $getFromSage = null,
        bool  $getError = false,
        bool  $ignorePingApi = false
    ): array|null|string
    {
        if (!is_null($this->pCatComptas)) {
            return $this->pCatComptas;
        }
        $entityName = SageEntityMenu::PCATCOMPTA_ENTITY_NAME;
        $cacheName = $useCache ? Sage::TOKEN . '_' . $entityName : null;
        $queryParams = [
            "filter_field" => [],
            "filter_type" => [],
            "filter_value" => [],
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
                list($tiers, $i) = preg_split('/(?<=.{' . $pos . '})/', str_replace('caCompta', '', $key), 2);
                $result[$tiers][(int)$i] = $pCatCompta;
            }
        } else {
            $result = $pCatComptas;
        }
        $this->pCatComptas = $result;
        return $this->pCatComptas;
    }

    private function _getPCatComptaSelectionSet(): array
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
}
