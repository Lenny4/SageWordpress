<?php

namespace App\services;

use StdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

if (!defined('ABSPATH')) {
    exit;
}

class SageService
{
    public final const CACHE_LIFETIME = 3600;

    private static ?SageService $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->cache = new FilesystemAdapter(defaultLifetime: self::CACHE_LIFETIME);
        }
        return self::$instance;
    }

    public function createAddressWithFComptet(StdClass $fComptet): StdClass
    {
        $r = new StdClass();
        $r->liIntitule = $fComptet->ctIntitule;
        $r->liAdresse = $fComptet->ctAdresse;
        $r->liComplement = $fComptet->ctComplement;
        $r->liCodePostal = $fComptet->ctCodePostal;
        $r->liPrincipal = 0;
        $r->liVille = $fComptet->ctVille;
        $r->liPays = $fComptet->ctPays;
        $r->liPaysCode = $fComptet->ctPaysCode;
        $r->liContact = $fComptet->ctContact;
        $r->liTelephone = $fComptet->ctTelephone;
        $r->liEmail = $fComptet->ctEmail;
        $r->liCodeRegion = $fComptet->ctCodeRegion;
        $r->liAdresseFact = 0;
        return $r;
    }

    public function getFirstNameLastName(...$fullNames): array
    {
        foreach ($fullNames as $fullName) {
            if (empty($fullName)) {
                continue;
            }
            $fullName = trim($fullName);
            $lastName = (!str_contains($fullName, ' ')) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $fullName);
            $firstName = trim(preg_replace('#' . preg_quote($lastName, '#') . '#', '', $fullName));
            return [$firstName, $lastName];
        }
        return ['', ''];
    }

    public function getName(?string $intitule, ?string $contact): string
    {
        $intitule = trim($intitule ?? '');
        $contact = trim($contact ?? '');
        $name = $intitule;
        if (empty($name)) {
            $name = $contact;
        }
        return $name;
    }

    public function getAvailableUserName(string $ctNum): string
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare("
SELECT user_login
FROM {$wpdb->users}
WHERE user_login LIKE %s
", [$ctNum . '%']));
        if (!empty($r)) {
            $names = array_map(static function (stdClass $user) {
                return $user->user_login;
            }, $r);
            $result = null;
            $i = 1;
            while (is_null($result)) {
                $newName = $ctNum . $i;
                if (!in_array($newName, $names, true)) {
                    $result = $newName;
                }
                $i++;
            }
            return $result;
        }
        return $ctNum;
    }
}
