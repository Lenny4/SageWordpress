<?php

namespace App\enum\Sage;

enum DocumentProvenanceTypeEnum: int
{
    case DocProvenanceNormale = 0;
    case DocProvenanceRetour = 1;
    case DocProvenanceAvoir = 2;
    case DocProvenanceTicket = 3;
    case DocProvenanceAcompte = 4;
}
