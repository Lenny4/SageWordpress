<?php

namespace App\lib;

use App\class\SageEntityMenu;
use App\enum\WebsiteEnum;
use App\Sage;
use App\SageSettings;
use Exception;
use GraphQL\Client;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\RawObject;
use GraphQL\Results;
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

    private function __construct(public ?Sage $sage)
    {
        $this->ping();
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

    public function addUpdateWebsite(
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
        $query = (new Mutation('addUpdateWebsite'))
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
        return $this->runQuery($query)?->getResults();
    }

    private function runQuery(Query|Mutation $gql): Results|null
    {
        $client = $this->getClient();
        try {
            return $client->runQuery($gql);
        } catch (Throwable $throwable) {
            add_action('admin_notices', static function () use ($throwable): void {
                ?>
                <div class="error"><p>
                        <?= $throwable->getMessage() ?>
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
            return $sageGraphQl->runQuery($query)?->getResults()?->data?->__type?->fields;
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
            $temps = $sageGraphQl->runQuery($query)?->getResults()?->data?->__type?->inputFields;
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

    public function getFArticle(string $arRef): StdClass|null
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
            [
                ...array_map(static function (string $field) {
                    return [
                        "name" => $field,
                        "type" => "StringOperationFilterInput",
                    ];
                }, ['arRef', 'arDesign']),
                [
                    "name" => "prices",
                ],
            ]
        );
        if (is_null($fArticle) || $fArticle->data->fArticles->totalCount !== 1) {
            return null;
        }

        return $fArticle->data->fArticles->items[0];
    }

    public function searchEntities(string $entityName, array $queryParams, array $fields, ?string $cacheName = null): StdClass|null
    {
        if (!is_null($cacheName)) {
            $cacheName = 'SearchEntities_' . $cacheName;
        }
        if (!$this->pingApi && !is_null($cacheName)) {
            $this->sage->cache->delete($cacheName);
            return null;
        }

        $sageGraphQl = $this;
        $function = static function () use ($entityName, $queryParams, $fields, $sageGraphQl) {
            $nbPerPage = (int)($queryParams["per_page"] ?? SageSettings::$defaultPagination);
            $page = (int)($queryParams["paged"] ?? 1);
            $where = [];
            if (array_key_exists('filter_field', $queryParams)) {
                $primaryFields = array_filter($fields, static fn(array $field): bool => array_key_exists('name', $field));
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

                    $where[$queryParams["filter_field"][$k]][] = $queryParams["filter_type"][$k] . ': ' . $queryParams["filter_value"][$k];
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
                            ->setSelectionSet($sageGraphQl->getSelectionSet($fields)),
                    ]
                );
            return $sageGraphQl->runQuery($query)?->getResults();
        };
        if (is_null($cacheName)) {
            return $function();
        }
        $results = $this->sage->cache->get($cacheName, $function);
        if (empty($results)) {
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
                return [SageEntityMenu::FDOCENTETE_DEFAULT_SORT, $defaultSortValue];
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

    private function getSelectionSet(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            if (is_numeric($key)) {
                if (!str_starts_with($value['name'], SageSettings::PREFIX_META_DATA)) {
                    $result[] = $value['name'];
                }
            } else {
                $result[] = (new Query($key))->setSelectionSet($this->getSelectionSet($value));
            }
        }
        return $result;
    }

    public function getFComptet(string $ctNum): StdClass|null
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
            [
                ...array_map(static function (string $field) {
                    return [
                        "name" => $field,
                        "type" => "StringOperationFilterInput",
                    ];
                }, [
                    'ctNum',
                    'ctIntitule',
                    'ctEmail',
                    'ctContact',
                    'ctAdresse',
                    'ctComplement',
                    'ctVille',
                    'ctCodePostal',
                    'ctPays',
                    'ctTelephone',
                    'ctCodeRegion',
                    'nCatTarif',
                ]),
                'fLivraisons' => [
                    ...array_map(static function (string $field) {
                        return [
                            "name" => $field,
                            "type" => "StringOperationFilterInput",
                        ];
                    }, [
                        'liIntitule',
                        'liAdresse',
                        'liComplement',
                        'liCodePostal',
                        'liPrincipal',
                        'liVille',
                        'liPays',
                        'liContact',
                        'liTelephone',
                        'liEmail',
                        'liAdresseFact',
                        'liCodeRegion',
                    ])
                ],
            ]
        );
        if (is_null($fComptet) || $fComptet->data->fComptets->totalCount !== 1) {
            return null;
        }

        return $fComptet->data->fComptets->items[0];
    }

    public function getPCattarifs(): array
    {
        $cacheName = SageEntityMenu::PCATTARIF_TYPE_MODEL;
        $pCattarifs = $this->searchEntities(
            SageEntityMenu::PCATTARIF_ENTITY_NAME,
            [
                "filter_field" => [
                    "ctIntitule"
                ],
                "filter_type" => [
                    "neq"
                ],
                "filter_value" => [
                    ''
                ],
                "paged" => "1",
                "per_page" => "100"
            ],
            [
                [
                    "name" => "cbMarq",
                ],
                [
                    "name" => "cbIndice",
                ],
                [
                    "name" => "ctIntitule",
                    "type" => "StringOperationFilterInput",
                ],
            ],
            $cacheName
        );
        $result = is_null($pCattarifs) ? [] : $pCattarifs->data->pCattarifs->items;
        usort($result, static function (stdClass $a, stdClass $b) {
            return $a->cbIndice <=> $b->cbIndice;
        });
        return $result;
    }
}
