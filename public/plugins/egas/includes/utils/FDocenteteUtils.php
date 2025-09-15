<?php

namespace App\utils;

use App\Sage;
use Symfony\Component\String\Slugger\AsciiSlugger;

if (!defined('ABSPATH')) {
    exit;
}

final class FDocenteteUtils
{
    // todo replace by DomaineTypeEnum
    public const DO_TYPE_DEVIS = 0;
    public const DO_TYPE_COMMANDE = 1;
    public const DO_TYPE_PREPA_LIVRAISON = 2;
    public const DO_TYPE_LIVRAISON = 3;
    public const DO_TYPE_REPRISE = 4;
    public const DO_TYPE_AVOIR = 5;
    public const DO_TYPE_FACTURE = 6;
    public const DO_TYPE_FACTURE_CPTA = 7;
    public const DO_TYPE_FACTURE_ARCHIVE = 8;

    public const DO_DOMAINE_VENTE = 0;

    public const DO_PROVENANCE_NORMAL = 0;

    // basically all doTypes which are saved in history or facture
    public const DO_TYPE_MAPPABLE = [
        self::DO_TYPE_DEVIS,
        self::DO_TYPE_COMMANDE,
        self::DO_TYPE_PREPA_LIVRAISON,
        self::DO_TYPE_LIVRAISON,
        self::DO_TYPE_FACTURE,
        self::DO_TYPE_FACTURE_CPTA,
    ];

    public const FDOCLIGNE_MAPPING_DO_TYPE = [
        self::DO_TYPE_DEVIS => 'De',
        self::DO_TYPE_COMMANDE => 'Bc',
        self::DO_TYPE_PREPA_LIVRAISON => 'Pl',
        self::DO_TYPE_LIVRAISON => 'Bl',
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
