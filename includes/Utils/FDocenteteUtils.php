<?php

namespace App\Utils;

use App\Sage;
use Symfony\Component\String\Slugger\AsciiSlugger;

if (!defined('ABSPATH')) {
    exit;
}

final class FDocenteteUtils
{
    public const VENTE_DEVIS = 0;
    public const VENTE_COMMANDE = 1;
    public const VENTE_PREPA_LIVRAISON = 2;
    public const VENTE_LIVRAISON = 3;
    public const VENTE_REPRISE = 4;
    public const VENTE_AVOIR = 5;
    public const VENTE_FACTURE = 6;
    public const VENTE_FACTURE_CPTA = 7;
    public const VENTE_FACTURE_ARCHIVE = 8;

    public const FDOCLIGNE_MAPPING_DO_TYPE = [
        self::VENTE_DEVIS => 'De',
        self::VENTE_COMMANDE => 'Bc',
        self::VENTE_PREPA_LIVRAISON => 'Pl',
        self::VENTE_LIVRAISON => 'Bl',
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
