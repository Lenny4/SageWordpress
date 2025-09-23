<?php

namespace App\enum\Sage;

enum ArticleTypeEnum: int
{
    case ArticleTypeStandard = 0;
    case ArticleTypeGamme = 1;
    case ArticleTypeRessourceUnitaire = 2;
    case ArticleTypeRessourceMultiple = 3;
}
