<?php

namespace App\enum\Sage;

enum NomenclatureTypeEnum: int
{
    case NomenclatureTypeAucun = 0;
    case NomenclatureTypeFabrication = 1;
    case NomenclatureTypeCompose = 2;
    case NomenclatureTypeComposant = 3;
    case NomenclatureTypeLies = 4;
}
