<?php

namespace App\enum\Sage;

enum DomaineTypeEnum: int
{
    case DomaineTypeVente = 0;
    case DomaineTypeAchat = 1;
    case DomaineTypeStock = 2;
    case DomaineTypeTicket = 3;
    case DomaineTypeInterne = 4;
}
