<?php

namespace App\resources;

use App\Sage;
use App\utils\ResourceUtils;

class FDocenteteResource extends Resource
{
    private static ?FDocenteteResource $instance = null;

    private function __construct()
    {
        $this->title = __("Documents", Sage::TOKEN);
        $this->entityName = ResourceUtils::FDOCENTETE_ENTITY_NAME;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
