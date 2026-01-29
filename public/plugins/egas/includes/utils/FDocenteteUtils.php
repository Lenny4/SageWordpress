<?php

namespace App\utils;

use App\enum\Sage\DocumentTypeEnum;
use App\Sage;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class FDocenteteUtils
{
    // basically all doTypes which are saved in history or facture
    public const DO_TYPE_MAPPABLE = [
        DocumentTypeEnum::DocumentTypeVenteDevis->value,
        DocumentTypeEnum::DocumentTypeVenteCommande->value,
        DocumentTypeEnum::DocumentTypeVentePrepaLivraison->value,
        DocumentTypeEnum::DocumentTypeVenteLivraison->value,
        DocumentTypeEnum::DocumentTypeVenteFacture->value,
        DocumentTypeEnum::DocumentTypeVenteFactureCpta->value,
    ];

    public const FDOCLIGNE_MAPPING_DO_TYPE = [
        DocumentTypeEnum::DocumentTypeVenteDevis->value => 'De',
        DocumentTypeEnum::DocumentTypeVenteCommande->value => 'Bc',
        DocumentTypeEnum::DocumentTypeVentePrepaLivraison->value => 'Pl',
        DocumentTypeEnum::DocumentTypeVenteLivraison->value => 'Bl',
    ];

    public const ALL_TAXES = [1, 2, 3];

    public static function getFdocligneMappingDoType(int $doType): string|null
    {
        return self::FDOCLIGNE_MAPPING_DO_TYPE[$doType] ?? null;
    }

    public static function slugifyPExpeditionEIntitule(string $eIntitule): string
    {
        $slugger = new AsciiSlugger();
        return Sage::TOKEN . '-' . strtolower($slugger->slug($eIntitule));
    }
}
