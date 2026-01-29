<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\ValueObject\PhpVersion;

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
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withRules([
        JsonThrowOnErrorRector::class,
    ]);
