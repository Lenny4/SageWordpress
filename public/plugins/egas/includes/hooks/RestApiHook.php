<?php

namespace App\hooks;

use App\controllers\WoocommerceController;
use App\resources\FComptetResource;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\WoocommerceService;
use App\services\WordpressService;
use App\utils\FDocenteteUtils;
use Automattic\WooCommerce\Admin\Overrides\Order;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

class RestApiHook
{
    public function __construct()
    {
        add_action('rest_api_init', static function () {
            register_rest_route(Sage::TOKEN . '/v1', '/search-entities/(?P<entityName>[A-Za-z0-9]+)', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $entityName = $request['entityName'];
                    $selectionSet = '_get' . ucfirst(substr($entityName, 0, -1)) . 'SelectionSet';
                    $graphqlService = GraphqlService::getInstance();
                    $result = $graphqlService->searchEntities(
                        $entityName,
                        $_GET,
                        $graphqlService->{$selectionSet}(),
                        ignorePingApi: true,
                    );
                    if (isset($result->data->{$entityName})) {
                        return new WP_REST_Response($result->data->{$entityName}, Response::HTTP_OK);
                    }
                    // todo return error message
                    return new WP_REST_Response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/search/sage-entity-menu/(?P<resourceName>[A-Za-z0-9]+)', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $resourceName = $request['resourceName'];
                    $resource = SageService::getInstance()->getResource($resourceName);
                    [
                        $data,
                        $showFields,
                        $filterFields,
                        $hideFields,
                        $perPage,
                        $queryParams,
                    ] = GraphqlService::getInstance()->getResourceWithQuery($resource);
                    if (isset($data["data"][$resourceName])) {
                        return new WP_REST_Response($data["data"][$resourceName], Response::HTTP_OK);
                    }
                    // todo return error message
                    return new WP_REST_Response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/farticle/(?P<arRef>([^&]*))/available', args: [ // https://stackoverflow.com/a/10126995/6824121
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    return new WP_REST_Response([
                        'availableArRef' => GraphqlService::getInstance()->getAvailableArRef(arRef: $request['arRef']),
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/orders/(?P<id>\d+)/sync', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $order = new WC_Order($request['id']);
                    $woocommerceService = WoocommerceService::getInstance();
                    $fDocenteteIdentifier = $woocommerceService->getFDocenteteIdentifierFromOrder($order);
                    $extendedFDocentetes = GraphqlService::getInstance()->getFDocentetes(
                        $fDocenteteIdentifier["doPiece"],
                        [$fDocenteteIdentifier["doType"]],
                        doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                        doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                        getError: true,
                        ignorePingApi: true,
                        getFDoclignes: true,
                        getExpedition: true,
                        addWordpressProductId: true,
                        getUser: true,
                        getLivraison: true,
                        extended: true,
                    );
                    $tasksSynchronizeOrder = $woocommerceService->getTasksSynchronizeOrder($order, $extendedFDocentetes);
                    [$message, $order] = $woocommerceService->applyTasksSynchronizeOrder($order, $tasksSynchronizeOrder);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => WoocommerceController::getMetaboxSage($order, ignorePingApi: true, message: $message),
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/farticle/(?P<arRef>([^&]*))/import', args: [ // https://stackoverflow.com/a/10126995/6824121
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $arRef = $request['arRef'];
                    $headers = [];
                    if (!empty($authorization = $request->get_header('authorization'))) {
                        $headers['authorization'] = $authorization;
                    }
                    [$response, $responseError, $message, $postId] = WoocommerceService::getInstance()->importFArticleFromSage(
                        $arRef,
                        ignorePingApi: true,
                        headers: $headers,
                    );
                    if ($request->get_param('json') === '1') {
                        $body = $response["body"];
                        try {
                            $body = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
                        } catch (Throwable) {
                            // nothing
                        }
                        return new WP_REST_Response($body, $response['response']['code']);
                    }
                    $order = new Order($request['orderId']);
                    return new WP_REST_Response([
                        'html' => WoocommerceController::getMetaboxSage(
                            $order,
                            ignorePingApi: true,
                            message: $message,
                        )
                    ], $response['response']['code']);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/fdocentetes/(?P<doPiece>[A-Za-z0-9]+$)', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $extended = false;
                    if (
                        array_key_exists('extended', $_GET) &&
                        ($_GET['extended'] === '1' || $_GET['extended'] === 'true')
                    ) {
                        $extended = true;
                    }
                    $fDocentetes = GraphqlService::getInstance()->getFDocentetes(
                        strtoupper(trim($request['doPiece'])),
                        doTypes: FDocenteteUtils::DO_TYPE_MAPPABLE,
                        doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                        doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                        getError: true,
                        ignorePingApi: true,
                        getWordpressIds: true,
                        extended: $extended,
                    );
                    if (is_string($fDocentetes)) {
                        return new WP_REST_Response([
                            'message' => $fDocentetes
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    if (is_null($fDocentetes)) {
                        return new WP_REST_Response([
                            'message' => 'Unknown error'
                        ], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    if ($fDocentetes === []) {
                        return new WP_REST_Response(null, Response::HTTP_NOT_FOUND);
                    }
                    return new WP_REST_Response($fDocentetes, Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/orders/(?P<id>\d+)/desynchronize', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $order = new WC_Order($request['id']);
                    $order = WoocommerceService::getInstance()->desynchronizeOrder($order);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => WoocommerceController::getMetaboxSage($order, ignorePingApi: true)
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/orders/(?P<id>\d+)/fdocentete', [
                'methods' => 'POST',
                'callback' => static function (WP_REST_Request $request) {
                    $order = new WC_Order($request['id']);
                    $body = json_decode($request->get_body(), false);
                    $doPiece = $body->{Sage::TOKEN . "-fdocentete-dopiece"};
                    $doType = (int)$body->{Sage::TOKEN . "-fdocentete-dotype"};
                    $order = WoocommerceService::getInstance()->linkOrderFDocentete($order, $doPiece, $doType, true);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => WoocommerceController::getMetaboxSage($order, ignorePingApi: true)
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/deactivate-shipping-zones', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    global $wpdb;
                    $wpdb->get_results($wpdb->prepare("
UPDATE {$wpdb->prefix}woocommerce_shipping_zone_methods
SET is_enabled = 0
WHERE method_id NOT LIKE '" . Sage::TOKEN . "%'
  AND is_enabled = 1
"));
                    $redirect = wp_get_referer();
                    wp_redirect($redirect);
                    exit();
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/add-website-sage-api', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $result = WordpressService::getInstance()->addWebsiteSageApi(true);
                    if ($result !== true) {
                        return new WP_REST_Response($result, Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    return new WP_REST_Response(null, Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/healthz', [
                'methods' => 'GET',
                'callback' => static function () {
                    return new WP_REST_Response(null, Response::HTTP_OK);
                },
                'permission_callback' => static function () {
                    return true;
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/orders/(?P<id>\d+)/meta-box-order', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    // this includes import woocommerce_wp_text_input
                    include_once __DIR__ . '/../../woocommerce/includes/admin/wc-meta-box-functions.php';
                    $order = new WC_Order($request['id']);
                    $orderHtml = WoocommerceController::getMetaBoxOrder($order);
                    $itemHtml = WoocommerceController::getMetaBoxOrderItems($order);
                    return new WP_REST_Response([
                        'orderHtml' => $orderHtml,
                        'itemHtml' => $itemHtml
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/fdocentetes/(?P<doPiece>[A-Za-z0-9]+)/(?P<doType>\d+)/import', args: [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $doPiece = $request['doPiece'];
                    $doType = $request['doType'];
                    $headers = [];
                    if (!empty($authorization = $request->get_header('authorization'))) {
                        $headers['authorization'] = $authorization;
                    }
                    [$message, $order] = WoocommerceService::getInstance()->importFDocenteteFromSage($doPiece, $doType, $headers);
                    return new WP_REST_Response([
                        'id' => $order->get_id(),
                        'message' => $message,
                    ], $message === "" ? Response::HTTP_CREATED : Response::HTTP_INTERNAL_SERVER_ERROR);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/user/(?P<ctNum>([^&]*))', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $ctNum = $request['ctNum'];
                    $fComptet = GraphqlService::getInstance()->getFComptet($ctNum, ignorePingApi: true);
                    $user = get_users([
                        'meta_key' => FComptetResource::META_KEY,
                        'meta_value' => strtoupper($ctNum)
                    ]);
                    if (!empty($user)) {
                        $user = $user[0];
                    } else {
                        $user = null;
                    }
                    return new WP_REST_Response([
                        'fComptet' => $fComptet,
                        'user' => $user,
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/import/(?P<entityName>[A-Za-z0-9]+)/(?P<identifier>.+)', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $resource = SageService::getInstance()->getResource($request['entityName']);
                    $postId = $resource->getImport()($request['identifier']);
                    return new WP_REST_Response([
                        'id' => $postId,
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
        });
    }
}
