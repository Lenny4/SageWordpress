<?php

namespace App\hooks;

use App\Sage;
use App\services\WordpressService;

class InstallPluginHook
{
    public function __construct()
    {
        $sage = Sage::getInstance();
        register_activation_hook($sage->file, function () {
            WordpressService::getInstance()->install();
        });
        register_deactivation_hook($sage->file, function () {
            flush_rewrite_rules();
        });
    }
}
