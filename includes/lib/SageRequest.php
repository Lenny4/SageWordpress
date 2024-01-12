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
            'sslverify' => false, // no ssl verification required for local request
            ...$params,
        ]);
    }

    public static function apiRequest(string $url): bool|string
    {
        $host = get_option(SageSettings::$base . 'api_host_url', null);
        $apiKey = get_option(SageSettings::$base . 'api_key', null);
        $curlHandle = curl_init();

        curl_setopt_array($curlHandle, [
            CURLOPT_URL => $host . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
//            CURLOPT_SSL_VERIFYPEER => !WP_DEBUG,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey,
            ]]);

        $response = curl_exec($curlHandle);

        curl_close($curlHandle);
        return $response;
    }
}
