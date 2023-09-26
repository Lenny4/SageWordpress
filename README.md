```
<?php
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return static function (RectorConfig $rectorConfig) {
// register single rule
    $rectorConfig->rule(TypedPropertyFromStrictConstructorRector::class);
    $rectorConfig->paths([
        __DIR__ . '/public',
    ]);
    $rectorConfig->skip([
        __DIR__ . '/public/languages',
        __DIR__ . '/public/mu-plugins',
        __DIR__ . '/public/plugins/gp-premium',
        __DIR__ . '/public/themes',
        __DIR__ . '/public/uploads',
        __DIR__ . '/public/wordpress',
    ]);

// here we can define, what sets of rules will be applied
// tip: use "SetList" class to autocomplete sets with your IDE
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::STRICT_BOOLEANS,
        SetList::GMAGICK_TO_IMAGICK,
        SetList::NAMING,
        SetList::PHP_82,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
    ]);
};

vendor/bin/rector process
```

https://github.com/hlashbrooke/WordPress-Plugin-Template

```
C:\xampp\htdocs\wordplate\public\plugins\sage>grunt
Running "less:compile" (less) task
>> 2 stylesheets created.

Running "cssmin:minify" (cssmin) task
>> Destination not written because minified CSS was empty.
>> Destination not written because minified CSS was empty.

Running "uglify:jsfiles" (uglify) task
File assets/js/admin.min.js created: 143 B → 38 B
File assets/js/frontend.min.js created: 146 B → 38 B
File assets/js/settings.min.js created: 2.42 kB → 1.15 kB

Done.
```
