<?php

namespace App\lib;

use App\SageSettings;
use WP_Error;
use WP_Http;

if (!defined('ABSPATH')) {
    exit;
}

final class SageRequest
{
    public static function selfRequest(
        string $url,
        array  $params,
    ): WP_Error|array
    {
        $url = wp_nonce_url(
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $url
            , 'wp_rest');
        return (new WP_Http)->request($url, [
            'timeout' => 30,
            'cookies' => $_COOKIE,
            ...$params,
        ]);
    }

    public static function apiRequest(string $url): bool|string
    {
        $host = get_option(SageSettings::$base . 'api_host_url', null);
        $apiKey = get_option(SageSettings::$base . 'api_key', null);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $host . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // todo SSL certificate problem: self-signed certificate, remove in prod
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }
}
