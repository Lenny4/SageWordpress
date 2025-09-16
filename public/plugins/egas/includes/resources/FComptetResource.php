<?php

namespace App\resources;

use App\Sage;
use App\utils\ResourceUtils;

class FComptetResource extends Resource
{
    private static ?FComptetResource $instance = null;

    private function __construct()
    {
        $this->title = __("Clients", Sage::TOKEN);
        $this->entityName = ResourceUtils::FCOMPTET_ENTITY_NAME;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
