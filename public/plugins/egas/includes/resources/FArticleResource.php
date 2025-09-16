<?php

namespace App\resources;

use App\Sage;
use App\utils\ResourceUtils;

class FArticleResource extends Resource
{
    private static ?FArticleResource $instance = null;

    private function __construct()
    {
        $this->title = __("Articles", Sage::TOKEN);
        $this->entityName = ResourceUtils::FARTICLE_ENTITY_NAME;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
