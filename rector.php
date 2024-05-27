<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    // https://github.com/rectorphp/rector/blob/main/docs/auto_import_names.md
    ->withImportNames()
//    ->withoutParallel()
    ->withPaths([
        __DIR__ . '/../../../wp-content/plugins/sage',
    ])
    ->withSkip([
        __DIR__ . '/../../../wp-content/plugins/sage/node_modules',
        __DIR__ . '/../../../wp-content/plugins/sage/vendor',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true,
    )
    ->withPhpSets(
        php82: true,
    );
