<?php

namespace App\enum\Sage;
enum DocumentFraisTypeEnum: int
{
    case DocFraisTypeForfait = 0;
    case DocFraisTypeQuantite = 1;
    case DocFraisTypePoidsNet = 2;
    case DocFraisTypePoidsBrut = 3;
    case DocFraisTypeColisage = 4;
}
