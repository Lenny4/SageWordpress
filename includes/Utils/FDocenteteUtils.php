<?php

namespace App\Utils;

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
        self::VENTE_DEVIS => 'dlPieceDe',
        self::VENTE_COMMANDE => 'dlPieceBc',
        self::VENTE_PREPA_LIVRAISON => 'dlPiecePl',
        self::VENTE_LIVRAISON => 'dlPieceBl',
    ];

    public static function getFdocligneMappingDoType(int $doType): string|null
    {
        return self::FDOCLIGNE_MAPPING_DO_TYPE[$doType] ?? null;
    }
}
