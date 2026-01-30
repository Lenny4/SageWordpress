<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Utils\Rector\Rector\JsonUnescapedUnicodeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
    ])
    ->withPreparedSets(
        deadCode: true,
//        codeQuality: true,
//        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
//        instanceOf: true,
//        earlyReturn: true,
//        strictBooleans: true,
//        carbon: true,
//        rectorPreset: true,
    )
    ->withPhpSets(php82: true)
    ->withRules([
        JsonUnescapedUnicodeRector::class,
    ]);
