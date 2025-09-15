<?php

namespace App\utils;

if (!defined('ABSPATH')) {
    exit;
}

final class RoundUtils
{
    public static function round(int|float|string $value): float
    {
        return round((float)$value * 100) / 100;
    }
}
