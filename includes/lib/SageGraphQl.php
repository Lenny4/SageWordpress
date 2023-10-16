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
        WebsiteEnum $type,
        string      $host,
        string      $protocol,
    ): StdClass|null
    {
        $mutation = (new Mutation('addUpdateWebsite'))
            ->setArguments([
                'name' => new RawObject('"' . $name . '"'),
                'username' => new RawObject('"' . $username . '"'),
                'password' => new RawObject('"' . $password . '"'),
                'type' => new RawObject(strtoupper($type->name)),
                'host' => new RawObject('"' . $host . '"'),
                'protocol' => new RawObject('"' . $protocol . '"'),
            ])
            ->setSelectionSet(
                [
                    'id',
                ]
            );
        return self::runQuery($mutation)?->getResults();
    }

    private static function runQuery(Query|Mutation $gql): Results|null
    {
        $client = self::getClient();
        try {
            return $client->runQuery($gql);
        } catch (Throwable $exception) {
            add_action('admin_notices', function () use ($exception) {
                ?>
                <div class="error"><p><?= __($exception->getMessage(), 'sage') ?></p></div>
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
}
