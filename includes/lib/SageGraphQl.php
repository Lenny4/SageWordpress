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
        $hostUrl = get_option(SageSettings::$base . 'api_host_url');
        if (!is_string($hostUrl) || empty($hostUrl)) {
            add_action('admin_notices', static function (): void {
                ?>
                <div class="notice notice-info">
                    <p>
                        <?= __('Veuillez renseigner l\'host du serveur Sage.', 'sage') ?>
                    </p>
                </div>
                <?php
            });
            $this->pingApi = false;
            return;
        }
        $curl = curl_init();
        $sslVerification = (bool)get_option(SageSettings::$base . 'activate_https_verification_graphql');
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
        curl_setopt_array($curl, $data);
        $response = curl_exec($curl);
        $errorMsg = null;
        if (curl_errno($curl)) {
            $errorMsg = curl_error($curl);
        }
        curl_close($curl);
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
            // todo what if get_option(SageSettings::$base . 'api_host_url') doesn't respond ?
                get_option(SageSettings::$base . 'api_host_url') . '/graphql',
                ['Api-Key' => get_option(SageSettings::$base . 'api_key')],
                [
                    'verify' => (bool)get_option(SageSettings::$base . 'activate_https_verification_graphql'),
                    'timeout' => 10, // vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php
                ]
            );
        }
        return $this->client;
    }

    public function searchEntities(string $entityName, array $queryParams, array $fields): StdClass|null
    {
        if (!$this->pingApi) {
            return null;
        }
        $rawFields = array_map(static fn(array $field) => $field['name'], $fields);
        $nbPerPage = (int)($queryParams["per_page"] ?? SageSettings::$defaultPagination);
        $page = (int)($queryParams["paged"] ?? 1);
        $where = [];
        if (array_key_exists('filter_field', $queryParams)) {
            foreach ($queryParams["filter_field"] as $k => $v) {
                $fieldType = current(array_filter($fields, static fn(array $field): bool => $field['name'] === $v))['type'];
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

            $arguments['where'] = new RawObject('{' . ($queryParams["where_condition"] ?? 'or') . ': {' . implode(',', $stringWhere) . '}}');
        }

        $query = (new Query($entityName))
            ->setArguments($arguments)
            ->setSelectionSet(
                [
                    'totalCount',
                    (new Query('items'))
                        ->setSelectionSet(
                            $rawFields
                        ),
                ]
            );
        return $this->runQuery($query)?->getResults();
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
            if ($queryParams['page'] === Sage::$_token . '_' . SageEntityMenu::FDOCENTETE_ENTITY_NAME) {
                return [SageEntityMenu::FDOCENTETE_DEFAULT_SORT, $defaultSortValue];
            }

            if ($queryParams['page'] === Sage::$_token . '_' . SageEntityMenu::FCOMPTET_ENTITY_NAME) {
                return [SageEntityMenu::FCOMPTET_DEFAULT_SORT, $defaultSortValue];
            }

            if ($queryParams['page'] === Sage::$_token . '_' . SageEntityMenu::FARTICLE_ENTITY_NAME) {
                return [SageEntityMenu::FARTICLE_DEFAULT_SORT, $defaultSortValue];
            }

            throw new Exception("Unknown page " . $queryParams['page']);
        }

        return [null, $defaultSortValue];
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
        $typeModel = $this->sage->cache->get($object, $function);
        if (empty($typeModel)) {
            $this->sage->cache->delete($cacheName);
            $typeModel = $this->sage->cache->get($object, $function);
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
            return $sageGraphQl->runQuery($query)?->getResults()?->data?->__type?->inputFields;
        };
        $typeModel = $this->sage->cache->get($object, $function);
        if (empty($typeModel)) {
            $this->sage->cache->delete($cacheName);
            $typeModel = $this->sage->cache->get($object, $function);
        }
        return $typeModel;
    }
}
