<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->importNames();
//    $rectorConfig->parallel(240); // https://github.com/rectorphp/rector/issues/7323
    $rectorConfig->disableParallel(); // https://github.com/rectorphp/rector/issues/7323
    $rectorConfig->paths([
        __DIR__ . '/../../..',
    ]);
    $rectorConfig->skip([
        __DIR__ . '/../../../wp-admin',
        __DIR__ . '/../../../wp-includes',

        __DIR__ . '/../../../index.php',
        __DIR__ . '/../../../wp-blog-header.php',
        __DIR__ . '/../../../wp-comments-post.php',
        __DIR__ . '/../../../wp-activate.php',
        __DIR__ . '/../../../wp-blog-header.phpwp-comments-post.php',
        __DIR__ . '/../../../wp-config.php',
        __DIR__ . '/../../../wp-config-docker.php',
        __DIR__ . '/../../../wp-config-sample.php',
        __DIR__ . '/../../../wp-cron.php',
        __DIR__ . '/../../../wp-links-opml.php',
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../../wp-login.php',
        __DIR__ . '/../../../wp-mail.php',
        __DIR__ . '/../../../wp-settings.php',
        __DIR__ . '/../../../wp-signup.php',
        __DIR__ . '/../../../wp-trackback.php',
        __DIR__ . '/../../../xmlrpc.php',

        __DIR__ . '/../../../wp-content/themes',
        __DIR__ . '/../../../wp-content/upgrade',
        __DIR__ . '/../../../wp-content/uploads',
        __DIR__ . '/../../../wp-content/index.php',

        __DIR__ . '/../../../wp-content/plugins/advanced-import',
        __DIR__ . '/../../../wp-content/plugins/elementor',
        __DIR__ . '/../../../wp-content/plugins/gradient-starter-templates',
        __DIR__ . '/../../../wp-content/plugins/phpinfo-wp',
        __DIR__ . '/../../../wp-content/plugins/woocommerce',
        __DIR__ . '/../../../wp-content/plugins/yith-woocommerce-quick-view',
        __DIR__ . '/../../../wp-content/plugins/index.php',

        __DIR__ . '/../../../wp-content/plugins/sage/node_modules',
        __DIR__ . '/../../../wp-content/plugins/sage/vendor',
    ]);

// here we can define, what sets of rules will be applied
// tip: use "SetList" class to autocomplete sets with your IDE
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        LevelSetList::UP_TO_PHP_82,
        SetList::CODING_STYLE,
        SetList::STRICT_BOOLEANS,
        SetList::GMAGICK_TO_IMAGICK,
        SetList::NAMING,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
    ]);
};
