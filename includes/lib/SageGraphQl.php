<?php

namespace App\lib;

use App\enum\WebsiteEnum;
use App\SageSettings;
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
    public static function addUpdateWebsite(
        string      $name,
        string      $username,
        string      $password,
        WebsiteEnum $websiteEnum,
        string      $host,
        string      $protocol,
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
            ])
            ->setSelectionSet(
                [
                    'id',
                ]
            );
        return self::runQuery($query)?->getResults();
    }

    private static function runQuery(Query|Mutation $gql): Results|null
    {
        $client = self::getClient();
        try {
            return $client->runQuery($gql);
        } catch (Throwable $throwable) {
            add_action('admin_notices', static function () use ($throwable): void {
                ?>
                <div class="error"><p>
                        <?= __($throwable->getMessage(), 'sage') ?>
                    </p></div>
                <?php
            });
        }

        return null;
    }

    private static function getClient(): Client
    {
        return new Client(
            get_option(SageSettings::$base . 'api_host_url') . '/graphql',
            ['Api-Key' => get_option(SageSettings::$base . 'api_key')]
        );
    }

    public static function searchEntities(string $entityName, array $queryParams, array $fields): StdClass|null
    {
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

        $order = '{ ctNum: ASC }';
        if (array_key_exists('sort', $queryParams)) {
            $json = json_decode(stripslashes((string)$queryParams['sort']), true, 512, JSON_THROW_ON_ERROR);
            $sortField = array_key_first($json);
            $order = '{ ' . $sortField . ': ' . strtoupper((string)$json[$sortField]) . ' }';
        }

        $arguments = [
            'skip' => $nbPerPage * ($page - 1),
            'take' => $nbPerPage,
            'order' => new RawObject($order),
        ];
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
        return self::runQuery($query)?->getResults();
    }

    // https://graphql.org/learn/introspection/
    public static function getTypeModel(string $object): StdClass|null
    {
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
        return self::runQuery($query)?->getResults();
    }

    public static function getTypeFilter(string $object): StdClass|null
    {
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
        return self::runQuery($query)?->getResults();
    }
}
