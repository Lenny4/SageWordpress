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

    public static function fComptets(array $queryParams, array $fields): StdClass|null
    {
        $query = (new Query('fComptets'))
            ->setArguments(['first' => 3])
            ->setSelectionSet(
                [
                    'totalCount',
                    (new Query('edges'))
                        ->setSelectionSet(
                            [
                                (new Query('node'))
                                    ->setSelectionSet(
                                        $fields
                                    ),
                            ],
                        ),
                ]
            );
        return self::runQuery($query)?->getResults();
    }

    // https://graphql.org/learn/introspection/
    public static function getType(string $object): StdClass|null
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
}
