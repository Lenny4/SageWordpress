<?php

namespace App\services;

use App\Sage;
use WP_Error;
use WP_Http;

if (!defined('ABSPATH')) {
    exit;
}

class RequestService
{
    private static ?RequestService $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function selfRequest(
        string $url,
        array  $params,
    ): WP_Error|array
    {
        // https://developer.wordpress.org/rest-api/key-concepts/
        // If you are using non-pretty permalinks, you should pass the REST API route as a query string parameter. The route http://oursite.com/wp-json/ in the example above would hence be http://oursite.com/?rest_route=/.
        $url = wp_nonce_url($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/index.php', 'wp_rest') . '&rest_route=' . urlencode($url);
        return (new WP_Http)->request($url, [
            'timeout' => 30,
            'cookies' => $_COOKIE,
            'sslverify' => false, // no ssl verification required for local request
            ...$params,
        ]);
    }

    public function apiRequest(string $url): bool|string
    {
        $host = get_option(Sage::TOKEN . '_api_host_url', null);
        $apiKey = get_option(Sage::TOKEN . '_api_key', null);
        $curlHandle = curl_init();

        curl_setopt_array($curlHandle, [
            CURLOPT_URL => $host . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey,
            ]]);

        $response = curl_exec($curlHandle);
        if ($response === false) {
            $errorCode = curl_errno($curlHandle);
            $errorMsg = curl_error($curlHandle);
            $response = $errorCode . " " . $errorMsg;
        }

        curl_close($curlHandle);
        return $response;
    }
}
