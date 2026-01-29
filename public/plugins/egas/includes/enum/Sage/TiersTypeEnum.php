<?php

declare(strict_types=1);

namespace App\enum\Sage;

enum TiersTypeEnum: int
{
    case TiersTypeClient = 0;
    case TiersTypeFournisseur = 1;
    case TiersTypeSalarie = 2;
    case TiersTypeAutre = 3;
}
