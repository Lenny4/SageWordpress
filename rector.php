<?php

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->importNames();
//    $rectorConfig->parallel(240); // https://github.com/rectorphp/rector/issues/7323
    $rectorConfig->disableParallel(); // https://github.com/rectorphp/rector/issues/7323
    $rectorConfig->paths([
        __DIR__ . '/../../../wp-content/plugins/sage',
    ]);
    $rectorConfig->skip([
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
