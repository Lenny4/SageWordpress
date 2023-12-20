https://developer.wordpress.org/rest-api/reference/application-passwords/#create-a-application-password

https://wordpress.stackexchange.com/questions/149212/how-to-create-pot-files-with-poedit

vendor/wp-cli/wp-cli/bin/wp i18n make-pot . lang/sage.pot

https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

https://www.elegantthemes.com/blog/tips-tricks/how-to-add-cron-jobs-to-wordpress

When add a new entity use function `private function settings_fields` with debugger to get all fields to translate.

```
<?php
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return static function (RectorConfig $rectorConfig) {
    $rectorConfig->importNames();
    $rectorConfig->parallel(240); // https://github.com/rectorphp/rector/issues/7323
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
