Api key: AD23A964-B01D-4BDB-93FF-B46940EA74B4
Api host url: 192.168.1.50
Wordpress host url: https://caddy
Wordpress db host: 192.168.1.44

# Important

launch chrome this way: `google-chrome --ignore-certificate-errors`

https://trello.com/b/t64T4Swz/sage-api

https://developer.wordpress.org/rest-api/reference/application-passwords/#create-a-application-password

https://wordpress.stackexchange.com/questions/149212/how-to-create-pot-files-with-poedit

vendor/wp-cli/wp-cli/bin/wp i18n make-pot . lang/sage.pot

https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

https://www.elegantthemes.com/blog/tips-tricks/how-to-add-cron-jobs-to-wordpress

When add a new entity use function `private function settings_fields` with debugger to get all fields to translate.

```
./runc wp-content/plugins/sage/vendor/bin/rector process --config=wp-content/plugins/sage/rector.php
```

Noter quelque part que l'erreur: Aucune connexion n’a pu être établie car l’ordinateur cible l’a expressément refusée,
correspond au fait qu'il faille ajouter s au http donc https pour `Wordpress host url`

https://github.com/hlashbrooke/WordPress-Plugin-Template

```
C:\xampp\htdocs\wordplate\public\plugins\sage>grunt --force
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

add `Screen Options` and `Help`:

```
add_action('admin_head', function () {

            //get the current screen object
            $current_screen = get_current_screen();

            // todo check $current_screen

            $current_screen->add_option('per_page', array(
                'label' => 'Show on page',
                'default' => 8,
                'option' => 'my_page_per_page', // the name of the option will be written in the user's meta-field
            ));

            //register our main help tab
            $current_screen->add_help_tab(array(
                    'id' => 'sp_basic_help_tab',
                    'title' => __('Basic Help Tab'),
                    'content' => '<p>Im a help tab, woo!</p>'
                )
            );

            //register our secondary help tab (with a callback instead of content)
//            $current_screen->add_help_tab(array(
//                    'id' => 'sp_help_tab_callback',
//                    'title' => __('Help Tab With Callback'),
//                    'callback' => function () {
//                        $content = '<p>This is text from our output function</p>';
//                        echo $content;
//                    }
//                )
//            );
        });
```
