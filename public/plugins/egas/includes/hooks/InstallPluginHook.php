<?php

namespace App\hooks;

use App\Sage;

class InstallPluginHook
{
    public function __construct()
    {
        $sage = Sage::getInstance();
        register_activation_hook($sage->file, function () {
            flush_rewrite_rules();
        });
        register_deactivation_hook($sage->file, function () {
            flush_rewrite_rules();
        });
    }
}
