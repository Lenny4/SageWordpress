<?php

namespace App\hooks;

use App\controllers\AdminController;

class WordpressHook
{
    public function __construct()
    {
        add_action('init', function () {

        });
        add_action('admin_menu', function (): void {
            AdminController::registerMenu();
        });
    }
}
