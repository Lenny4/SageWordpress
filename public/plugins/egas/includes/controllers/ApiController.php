<?php

namespace App\controllers;

use App\Sage;
use Symfony\Component\HttpFoundation\Response;
use WP_REST_Request;
use WP_REST_Response;

class ApiController
{
    public function __construct()
    {

    }

    public function registerRoutes(): void
    {
        register_rest_route(Sage::TOKEN . '/v1', '/orders/(?P<id>\d+)/desynchronize', [
            'methods' => 'GET',
            'callback' => static function (WP_REST_Request $request) {
                return new WP_REST_Response(true, Response::HTTP_OK);
            },
            'permission_callback' => static function (WP_REST_Request $request) {
                return current_user_can('manage_options');
            },
        ]);
    }
}
