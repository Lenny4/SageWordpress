<?php

namespace App\Utils;

if (!defined('ABSPATH')) {
    exit;
}

final class PCatComptaUtils
{
    public const TIERS_TYPE_VEN = 'Ven';
    public const TIERS_TYPE_ACH = 'Ach';
    public const TIERS_TYPE_STO = 'Sto';

    public const NB_TIERS_TYPE = 50;

    public const ALL_TIERS_TYPE = [
        self::TIERS_TYPE_VEN,
        self::TIERS_TYPE_ACH,
        self::TIERS_TYPE_STO,
    ];
}
