<?php

namespace App\utils;

use App\Sage;
use stdClass;

final class PathUtils
{
    public static function getByPath(stdClass $obj, string $path): stdClass|null
    {
        $current = $obj;

        // Découpe le chemin en segments, par exemple : 'fArtclients[1].cars[red]' => ['fArtclients[1]', 'cars[red]']
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (empty($segment)) {
                continue;
            }
            // Vérifie si le segment contient une clé entre crochets, par exemple : fArtclients[1]
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\[(.*?)\]$/', $segment, $matches)) {
                $prop = $matches[1]; // ex: fArtclients
                $key = $matches[2];  // ex: 1

                // Accéder à la propriété si elle existe
                if (is_object($current) && isset($current->$prop)) {
                    $current = $current->$prop;
                } else {
                    return null;
                }
                // Accéder à l'élément du tableau
                if (is_array($current) && isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    return null;
                }
            } elseif (is_object($current) && isset($current->$segment)) {
                $current = $current->$segment;
            } else {
                return null;
            }
        }

        return $current;
    }

    public static function flatternPostSageData(array $post, string $prefix = ''): array
    {
        $result = [];
        foreach ($post as $key => $value) {
            if (is_array($value) && (str_starts_with($key, '_' . Sage::TOKEN . '_') || !empty($prefix))) {
                foreach ($value as $k => $v) {
                    $result = [
                        ...$result,
                        ...self::flatternPostSageData($v, $key . '[' . $k . '].'),
                    ];
                }
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
