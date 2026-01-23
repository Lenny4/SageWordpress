<?php

namespace App\hooks;

use App\controllers\WoocommerceController;
use App\enum\Sage\DocumentProvenanceTypeEnum;
use App\enum\Sage\DomaineTypeEnum;
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
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RestApiHook
{
    public function __construct()
    {
        // add meta_data for .line_items for request https://localhost/?rest_route=/wc/v2/orders/1996
        add_filter('woocommerce_rest_prepare_shop_order_object', function ($response, $order, $request) {
            $data = $response->get_data();
            if (empty($data['line_items'])) {
                return $response;
            }
            foreach ($data['line_items'] as $index => $li) {
                if (empty($li['product_id'])) {
                    continue;
                }
                $product_id = (int)$li['product_id'];
                $meta = get_post_meta($product_id);
                $metaDataList = [];
                foreach ($meta as $meta_key => $values) {
                    $value = maybe_unserialize($values[0]);
                    if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $value = $decoded;
                        }
                    }
                    $metaDataList[] = [
                        'key' => $meta_key,
                        'value' => maybe_unserialize($value ?? null),
                    ];
                }
                $existing = $data['line_items'][$index]['meta_data'] ?? [];
                $data['line_items'][$index]['meta_data'] = array_merge($existing, $metaDataList);
            }
            $response->set_data($data);
            return $response;
        }, 10, 3);
        add_filter('rest_pre_dispatch', function ($result) {
            GraphqlService::getInstance()->ping();
            return $result; // must return $result
        });
        add_action('rest_api_init', function () {
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
                    return new WP_REST_Response($data, Response::HTTP_SERVICE_UNAVAILABLE);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            register_rest_route(Sage::TOKEN . '/v1', '/farticles/(?P<arRef>([^&]*))/available', args: [ // https://stackoverflow.com/a/10126995/6824121
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
                        doDomaine: DomaineTypeEnum::DomaineTypeVente->value,
                        doProvenance: DocumentProvenanceTypeEnum::DocProvenanceNormale->value,
                        getError: true,
                        getFDoclignes: true,
                        getExpedition: true,
                        addWordpressProductId: true,
                        getUser: true,
                        getLivraison: true,
                        getLotSerie: true,
                        extended: true,
                    );
                    $tasksSynchronizeOrder = $woocommerceService->getTasksSynchronizeOrder($order, $extendedFDocentetes);
                    [$message, $order] = $woocommerceService->applyTasksSynchronizeOrder($order, $tasksSynchronizeOrder);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => WoocommerceController::getMetaboxFDocentete($order, message: $message),
                    ], Response::HTTP_OK);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            // todo supprimer ? utiliser seulement /import/(?P<entityName>[A-Za-z0-9]+)/(?P<identifier>.+) voir du côté de l'api Uri.EscapeDataString("/" + DbToken + "/v1/import si c'est possible
            register_rest_route(Sage::TOKEN . '/v1', '/farticles/(?P<arRef>([^&]*))/import', args: [ // https://stackoverflow.com/a/10126995/6824121
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $arRef = $request['arRef'];
                    [$response, $responseError, $message, $postId] = WoocommerceService::getInstance()->importFArticleFromSage(
                        $arRef,
                    );
                    if ($request->get_param('json') === '1') {
                        if ($response instanceof WP_Error) {
                            $body = json_encode($response->get_error_messages());
                            $code = $response->get_error_code();
                        } else if (is_null($response) || is_int($response)) {
                            return new WP_REST_Response(json_encode([
                                'responseError' => $responseError,
                                'message' => $message,
                            ]), is_int($response) ? $response : Response::HTTP_INTERNAL_SERVER_ERROR);
                        } else {
                            $body = $response["body"];
                            $code = $response['response']['code'];
                            try {
                                $body = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
                            } catch (Throwable) {
                                // nothing
                            }
                        }
                        return new WP_REST_Response($body, $code);
                    }
                    $order = new Order($request['orderId']);
                    return new WP_REST_Response([
                        'html' => WoocommerceController::getMetaboxFDocentete(
                            $order,
                            message: $message,
                        )
                    ], $response['response']['code']);
                },
                'permission_callback' => static function (WP_REST_Request $request) {
                    return current_user_can('manage_options');
                },
            ]);
            // todo supprimer ? utiliser seulement /import/(?P<entityName>[A-Za-z0-9]+)/(?P<identifier>.+), plutot faire les register rest route sur les resources directement
            register_rest_route(Sage::TOKEN . '/v1', '/fdocentetes/(?P<doPiece>[A-Za-z0-9]+)/(?P<doType>\d+)/import', args: [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $doPiece = $request['doPiece'];
                    $doType = $request['doType'];
                    $orderId = $request->get_param('orderId');
                    [$message, $order] = WoocommerceService::getInstance()->importFDocenteteFromSage($doPiece, $doType, $orderId);
                    return new WP_REST_Response([
                        'id' => $order->get_id(),
                        'message' => $message,
                    ], $message === "" ? Response::HTTP_CREATED : Response::HTTP_INTERNAL_SERVER_ERROR);
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
                        doDomaine: DomaineTypeEnum::DomaineTypeVente->value,
                        doProvenance: DocumentProvenanceTypeEnum::DocProvenanceNormale->value,
                        getError: true,
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
                        'html' => WoocommerceController::getMetaboxFDocentete($order)
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
                    $order = WoocommerceService::getInstance()->linkOrderFDocentete($order, $doPiece, $doType);
                    return new WP_REST_Response([
                        // we create a new order here to be sure to refresh all data from bdd
                        'html' => WoocommerceController::getMetaboxFDocentete($order)
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
            register_rest_route(Sage::TOKEN . '/v1', '/user/(?P<ctNum>([^&]*))', [
                'methods' => 'GET',
                'callback' => static function (WP_REST_Request $request) {
                    $ctNum = $request['ctNum'];
                    $fComptet = GraphqlService::getInstance()->getFComptet($ctNum);
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
            $this->expose_all_user_meta_in_rest(); // must be call after all register_rest_route
        });
    }

    private function expose_all_user_meta_in_rest(): void
    {
        $sage = Sage::getInstance();
        $plugin_data = get_plugin_data($sage->file);
        $version = $plugin_data['Version'];
        $cache_key = Sage::TOKEN . '_all_user_meta_keys_' . md5($version);
        $meta_keys = get_transient($cache_key);
        if (empty($meta_keys)) {
            global $wpdb;
            $meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->usermeta}");
            if (!empty($meta_keys)) {
                set_transient($cache_key, $meta_keys, 24 * 3_600);
            }
        }
        if (!empty($meta_keys)) {
            foreach ($meta_keys as $key) {
                register_meta('user', $key, [
                    'show_in_rest' => true,
                    'single' => true,
                    'type' => 'string',
                ]);
            }
        }
    }
}
