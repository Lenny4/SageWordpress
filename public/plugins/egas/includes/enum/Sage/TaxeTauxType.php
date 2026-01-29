<?php

declare(strict_types=1);

namespace App\enum\Sage;
/**
 * - TaxeTauxType.TaxeTauxTypePourcent: pourcentage du prix
 * - TaxeTauxType.TaxeTauxTypeMontant: montant fixe ajouté à chaque ligne du document de vente quelque soit la quantité du produit
 * - TaxeTauxType.TaxeTauxTypeQuantite: montant fixe ajouté à chaque produit
 */
enum TaxeTauxType: int
{
    case TaxeTauxTypePourcent = 0;
    case TaxeTauxTypeMontant = 1;
    case TaxeTauxTypeQuantite = 2;
}
