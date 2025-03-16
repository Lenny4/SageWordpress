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
        $message = null;
        if (!is_string($hostUrl) || ($hostUrl === '' || $hostUrl === '0')) {
            $message = __("Veuillez renseigner l'host du serveur Sage.", 'sage');
        } else if (filter_var($hostUrl, FILTER_VALIDATE_URL) === false) {
            $message = __("L'host du serveur Sage n'est pas une url valide.", 'sage');
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
                <div class="error"><p>
                        <?= __("L'API Sage n'est pas joignable. Avez vous lancé le serveur ?", 'sage') ?>
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
        string $username,
        string $password,
    ): StdClass|null
    {
        global $wpdb;
        $hasError = false;
        $wordpressHostUrl = parse_url((string)get_option(Sage::TOKEN . '_wordpress_host_url'));
        if (!array_key_exists("scheme", $wordpressHostUrl)) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="error"><p>
                    <?= __("Wordpress host url doit commencer par 'http://' ou 'https://'", 'sage') ?>
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
                    <?= __("Api host url doit commencer par 'http://' ou 'https://'", 'sage') ?>
                </p>
                </div><?php
            });
            $hasError = true;
        }
        if ($hasError) {
            return null;
        }
        $autoImportSageFcomptet = SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_sage_fcomptet');
        $autoImportWordpressAccount = SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_wordpress_account');
        $autoImportWordpressOrderDate = SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_wordpress_order_date');
        $autoImportWordpressOrderDoType = empty($autoImportWordpressOrderDoType = get_option(Sage::TOKEN . '_auto_import_wordpress_order_dotype')) ? null : $autoImportWordpressOrderDoType;
        $autoCreateWordpressOrder = empty($autoCreateWordpressOrder = get_option(Sage::TOKEN . '_auto_create_wordpress_order')) ? null : $autoCreateWordpressOrder;
        $autoImportWordpressArticle = SageSettings::get_option_date_or_null(Sage::TOKEN . '_auto_import_wordpress_article');
        $query = (new Mutation('createUpdateWebsite'))
            ->setArguments([
                'name' => new RawObject('"' . get_bloginfo() . '"'),
                'username' => new RawObject('"' . $username . '"'),
                'password' => new RawObject('"' . $password . '"'),
                'type' => new RawObject(strtoupper(WebsiteEnum::Wordpress->name)),
                'host' => new RawObject('"' . $wordpressHostUrl["host"] . '"'),
                'protocol' => new RawObject('"' . $wordpressHostUrl["scheme"] . '"'),
                'forceSsl' => new RawObject(get_option(Sage::TOKEN . '_activate_https_verification_wordpress') ? 'true' : 'false'),
                'dbHost' => new RawObject('"' . get_option(Sage::TOKEN . '_wordpress_db_host') . '"'),
                'dbUsername' => new RawObject('"' . get_option(Sage::TOKEN . '_wordpress_db_username') . '"'),
                'dbPassword' => new RawObject('"' . get_option(Sage::TOKEN . '_wordpress_db_password') . '"'),
                'tablePrefix' => new RawObject('"' . $wpdb->prefix . '"'),
                'dbName' => new RawObject('"' . get_option(Sage::TOKEN . '_wordpress_db_name') . '"'),
                'autoCreateSageFcomptet' => new RawObject(get_option(Sage::TOKEN . '_auto_create_sage_fcomptet') ? 'true' : 'false'),
                'autoImportSageFcomptet' => new RawObject(!is_null($autoImportSageFcomptet) ? '"' . $autoImportSageFcomptet->format('Y-m-d H:i:s') . '"' : 'null'),
                'autoCreateWebsiteAccount' => new RawObject(get_option(Sage::TOKEN . '_auto_create_wordpress_account') ? 'true' : 'false'),
                'autoImportWebsiteAccount' => new RawObject(!is_null($autoImportWordpressAccount) ? '"' . $autoImportWordpressAccount->format('Y-m-d H:i:s') . '"' : 'null'),
                'autoCreateSageFdocentete' => new RawObject(get_option(Sage::TOKEN . '_auto_create_sage_fdocentete') ? 'true' : 'false'),
                'autoImportWebsiteOrderDate' => new RawObject(!is_null($autoImportWordpressOrderDate) ? '"' . $autoImportWordpressOrderDate->format('Y-m-d H:i:s') . '"' : 'null'),
                'autoImportWebsiteOrderDoType' => new RawObject(is_array($autoImportWordpressOrderDoType) ? json_encode($autoImportWordpressOrderDoType, JSON_THROW_ON_ERROR) : 'null'),
                'autoCreateWebsiteOrder' => new RawObject(is_array($autoCreateWordpressOrder) ? json_encode($autoCreateWordpressOrder, JSON_THROW_ON_ERROR) : 'null'),
                'autoCreateWebsiteArticle' => new RawObject(get_option(Sage::TOKEN . '_auto_create_wordpress_article') ? 'true' : 'false'),
                'autoImportWebsiteArticle' => new RawObject(!is_null($autoImportWordpressArticle) ? '"' . $autoImportWordpressArticle->format('Y-m-d H:i:s') . '"' : 'null'),
                'pluginVersion' => get_plugin_data($this->sage->file)['Version'],
            ])
            ->setSelectionSet(
                [
                    'id',
                    'authorization',
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

    public function createFComptet(
        int     $userId,
        ?string $ctNum = null,
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
            'ctIntitule' => new RawObject('"' . $ctIntitule . '"'),
            'ctEmail' => new RawObject('"' . $ctEmail . '"'),
            'websiteId' => new RawObject('"' . get_option(Sage::TOKEN . '_website_id') . '"'),
        ];
        if (!is_null($ctNum)) {
            $arguments['ctNum'] = new RawObject('"' . $ctNum . '"');
        }
        $arguments['autoGenerateCtNum'] = new RawObject($autoGenerateCtNum ? 'true' : 'false');
        $query = (new Mutation('createFComptet'))
            ->setArguments($arguments)
            ->setSelectionSet($this->formatSelectionSet($this->_getFComptetSelectionSet()));
        $result = $this->runQuery($query, $getError);
        if (!is_null($result) && !is_string($result)) {
            return $result->data->createFComptet;
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
                'nCatCompta',
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
                $entities = (array)json_decode($entities, false, 512, JSON_THROW_ON_ERROR);
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
                    } else {
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
                $stringWhere = implode(',', $where);
                $arguments['where'] = new RawObject('{' . ($queryParams["where_condition"] ?? 'or') . ': [' . $stringWhere . ']}');
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
//        $this->pExpeditions = null;
//        $useCache = false;
//        $getFromSage = true;
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

    private function _getFArticleSelectionSet(bool $forExpedition = false): array
    {
        if ($forExpedition) {
            return [
                ...$this->_formatOperationFilterInput("StringOperationFilterInput", ['arRef']),
            ];
        }
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", ['arPoidsNet', 'arPoidsBrut']),
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

    private function _getFTaxeSelectionSet(): array
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

    private function _getFExpeditiongrilles(): array
    {
        return [
            ...$this->_formatOperationFilterInput("IntOperationFilterInput", [
                'egBorne',
                'egFrais',
            ]),
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

    private function _getFDocenteteSelectionSet(
        bool $getFDoclignes = false,
        bool $getExpedition = false,
        bool $getUser = false,
        bool $getLivraison = false,
        bool $getLotSerie = false,
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
            $result['fDoclignes'] = $this->_getFDocligneSelectionSet(getLotSerie: $getLotSerie);
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

    private function _getFDocligneSelectionSet(
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
            $r['fLotserieNavigation'] = [
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
        $pCattarifs = $this->getEntitiesAndSaveInOption(
            $cacheName,
            $getFromSage,
            $entityName,
            $queryParams,
            $selectionSets,
            $getError,
            $ignorePingApi
        );
        $this->pCattarifs = [];
        if (is_array($pCattarifs)) {
            foreach ($pCattarifs as $pCattarif) {
                $this->pCattarifs[$pCattarif->cbIndice] = $pCattarif;
            }
        } else {
            $this->pCattarifs = $pCattarifs;
        }
        return $this->pCattarifs;
    }

    private function _getPCattarifSelectionSet(): array
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

    public function updateFComptetFromWebsite(
        string $ctNum,
        bool   $getError = false,
    ): StdClass|null|string
    {
        $arguments = [
            'ctNum' => new RawObject('"' . $ctNum . '"'),
            'websiteId' => new RawObject('"' . get_option(Sage::TOKEN . '_website_id') . '"'),
        ];
        $query = (new Mutation('updateFComptetFromWebsite'))
            ->setArguments($arguments)
            ->setSelectionSet($this->formatSelectionSet($this->_getFComptetSelectionSet()));
        $result = $this->runQuery($query, $getError);
        if (!is_null($result) && !is_string($result)) {
            return $result->data->updateFComptetFromWebsite;
        }
        return $result;
    }
}
