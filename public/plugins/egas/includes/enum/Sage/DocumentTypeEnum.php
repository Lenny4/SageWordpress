<?php

namespace App\enum\Sage;

enum DocumentTypeEnum: int
{
    case DocumentTypeVenteDevis = 0;
    case DocumentTypeVenteCommande = 1;
    case DocumentTypeVentePrepaLivraison = 2;
    case DocumentTypeVenteLivraison = 3;
    case DocumentTypeVenteReprise = 4;
    case DocumentTypeVenteAvoir = 5;
    case DocumentTypeVenteFacture = 6;
    case DocumentTypeVenteFactureCpta = 7;
}
