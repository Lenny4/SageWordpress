https://developer.wordpress.org/rest-api/reference/application-passwords/#create-a-application-password

https://wordpress.stackexchange.com/questions/149212/how-to-create-pot-files-with-poedit

vendor/wp-cli/wp-cli/bin/wp i18n make-pot . lang/sage.pot

https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

https://www.elegantthemes.com/blog/tips-tricks/how-to-add-cron-jobs-to-wordpress

When add a new entity use function `private function settings_fields` with debugger to get all fields to translate.

```
./runc wp-content/plugins/sage/vendor/bin/rector process --config=wp-content/plugins/sage/rector.php
```

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



[
'id' => 'auto_send_mail_import_sage_account',
'label' => __('Automatically send email to reset password', 'sage'),
'description' => __("Lorsqu'un compte Wordpress est créé à partir d'un compte Sage un mail pour définir le mot de passe du compte Wordpress est automatiquement envoyé à l'utilisateur.", 'sage'),
'type' => 'checkbox',
'default' => 'on'
],
- ajouter l'option: "Envoyer un mail à l'utilisateur lorsque son compte Sage a été importé dans Wordpress" -> send
  wordpress function reset password
  // Verify user capabilities.
  if ( ! current_user_can( 'edit_user', $user_id ) ) {
  wp_send_json_error( __( 'Cannot send password reset, permission denied.' ) );
  }
  // Send the password reset link.
  $user = get_userdata( $user_id );
  $results = retrieve_password( $user->user_login );

- le site doit pouvoir marcher même si l'API est down (un utilisateur doit pouvoir ce connecter et passer commande)

- logger tous les appels API en erreur
- 
