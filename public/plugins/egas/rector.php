<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
    ])
    // A. whole set
    ->withPreparedSets(
//        deadCode: true,
//        codeQuality: true,
//        codingStyle: true,
        typeDeclarations: true,
//        privatization: true,
//        instanceOf: true,
//        earlyReturn: true,
//        strictBooleans: true,
//        carbon: true,
//        rectorPreset: true,
    )
    // demonstrate specific PHP version
    ->withPhpVersion(PhpVersion::PHP_82);
