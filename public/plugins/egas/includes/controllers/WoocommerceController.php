<?php

namespace App\controllers;

use App\Sage;
use App\services\GraphqlService;
use App\services\TwigService;
use App\services\WoocommerceService;
use App\Utils\FDocenteteUtils;
use App\Utils\PCatComptaUtils;
use App\utils\SageTranslationUtils;
use PHPHtmlParser\Dom;
use WC_Meta_Box_Order_Data;
use WC_Order;

class WoocommerceController
{
    public static function addColumn(array $columns): array
    {
        $columns[Sage::TOKEN] = __('Sage', Sage::TOKEN);
        return $columns;
    }

    public static function displayColumn(string $column_name, WC_Order $order): string
    {
        $trans = SageTranslationUtils::getTranslations();
        if (Sage::TOKEN !== $column_name) {
            return '';
        }
        $identifier = WoocommerceService::getInstance()->getFDocenteteIdentifierFromOrder($order);
        if (empty($identifier)) {
            return '<span class="dashicons dashicons-no" style="color: red"></span>';
        }
        return $trans["fDocentetes"]["doType"]["values"][$identifier['doType']]
            . ': n° '
            . $identifier["doPiece"];
    }

    public static function getMetaboxSage(WC_Order $order, bool $ignorePingApi = false, string $message = '')
    {
        $woocommerceService = WoocommerceService::getInstance();
        $graphqlService = GraphqlService::getInstance();
        $fDocenteteIdentifier = $woocommerceService->getFDocenteteIdentifierFromOrder($order);
        $hasFDocentete = !is_null($fDocenteteIdentifier);
        $extendedFDocentetes = null;
        $tasksSynchronizeOrder = [];
        if ($hasFDocentete) {
            $extendedFDocentetes = $graphqlService->getFDocentetes(
                $fDocenteteIdentifier["doPiece"],
                [$fDocenteteIdentifier["doType"]],
                doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                getError: true,
                ignorePingApi: $ignorePingApi,
                getFDoclignes: true,
                getExpedition: true,
                addWordpressProductId: true,
                getUser: true,
                getLivraison: true,
                addWordpressUserId: true,
                getLotSerie: true,
                extended: true,
            );
            if (is_string($extendedFDocentetes)) {
                $message .= $extendedFDocentetes;
            }
            $tasksSynchronizeOrder = $woocommerceService->getTasksSynchronizeOrder($order, $extendedFDocentetes);
        }
        // original WC_Meta_Box_Order_Data::output
        $pCattarifs = $graphqlService->getPCattarifs();
        $pCatComptas = $graphqlService->getPCatComptas();
        return TwigService::getInstance()->render('woocommerce/metaBoxes/main.html.twig', [
            'message' => $message,
            'doPieceIdentifier' => $fDocenteteIdentifier ? $fDocenteteIdentifier["doPiece"] : null,
            'doTypeIdentifier' => $fDocenteteIdentifier ? $fDocenteteIdentifier["doType"] : null,
            'order' => $order,
            'hasFDocentete' => $hasFDocentete,
            'extendedFDocentetes' => $extendedFDocentetes,
            'currency' => get_woocommerce_currency(),
            'fdocligneMappingDoType' => FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE,
            'tasksSynchronizeOrder' => $tasksSynchronizeOrder,
            'pCattarifs' => $pCattarifs,
            'pCatComptas' => $pCatComptas[PCatComptaUtils::TIERS_TYPE_VEN],
        ]);
    }

    public static function getMetaBoxOrder(WC_Order $order, ?callable $callback = null): string
    {
        ob_start();
        if (is_null($callback)) {
            WC_Meta_Box_Order_Data::output($order);
        } else {
            $callback($order);
        }
        $dom = new Dom(); // https://github.com/paquettg/php-html-parser?tab=readme-ov-file#modifying-the-dom
        $dom->loadStr(ob_get_clean());
        $fDocenteteIdentifier = WoocommerceService::getInstance()->getFDocenteteIdentifierFromOrder($order);
        $translations = SageTranslationUtils::getTranslations();
        if (!empty($fDocenteteIdentifier)) {
            $a = $dom->find('.woocommerce-order-data__heading')[0];
            $title = $a->innerHtml();
            return str_replace($title, $title . '['
                . $translations["fDocentetes"]["doType"]["values"][$fDocenteteIdentifier["doType"]]
                . ': n° '
                . $fDocenteteIdentifier["doPiece"]
                . ']', $dom);
        }
        return $dom;
    }

    public static function getMetaBoxOrderItems(WC_Order $order): string
    {
        ob_start();
        include __DIR__ . '/../../woocommerce/includes/admin/meta-boxes/views/html-order-items.php';
        return ob_get_clean();
    }
}
