<?php

namespace App\controllers;

use App\enum\Sage\DocumentProvenanceTypeEnum;
use App\enum\Sage\DomaineTypeEnum;
use App\Sage;
use App\services\GraphqlService;
use App\services\SageService;
use App\services\TwigService;
use App\services\WoocommerceService;
use App\utils\FDocenteteUtils;
use App\utils\PCatComptaUtils;
use App\utils\SageTranslationUtils;
use Symfony\Component\DomCrawler\Crawler;
use WC_Meta_Box_Order_Data;
use WC_Order;
use WP_Post;

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

    public static function getMetaboxFDocentete(WC_Order $order, bool $ignorePingApi = false, string $message = '')
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
                doDomaine: DomaineTypeEnum::DomaineTypeVente->value,
                doProvenance: DocumentProvenanceTypeEnum::DocProvenanceNormale->value,
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

    public static function getMetaBoxOrderItems(WC_Order $order): string
    {
        ob_start();
        include __DIR__ . '/../../woocommerce/includes/admin/meta-boxes/views/html-order-items.php';
        return ob_get_clean();
    }

    public static function showMetaBoxProduct(array $wp_meta_boxes, string $screen): void
    {
        $arRef = SageService::getInstance()->getArRef(get_the_ID());
        $id = 'woocommerce-product-data';
        $context = 'normal';
        remove_meta_box($id, $screen, $context);

        $callback = $wp_meta_boxes[$screen][$context]["high"][$id]["callback"];
        add_meta_box($id, __('Product data', 'woocommerce'), static function (WP_Post $wpPost) use ($arRef, $callback): void {
            ob_start();
            $callback($wpPost);
            $html = ob_get_clean();
            $crawler = new Crawler($html);
            $a = $crawler->filter('span.product-data-wrapper')->first();
            $content = $a->outerHtml();
            $hasArRef = !empty($arRef);
            $labelArRef = '';
            if ($hasArRef) {
                $labelArRef = ': <span style="display: initial" class="h4">' . $arRef . '</span>';
            }
            $content = str_replace($content, $labelArRef . $content, $html);
            if ($hasArRef || str_contains($wpPost->post_status, 'draft')) {
                $content = str_replace(
                    ["selected='selected'", "option value=" . Sage::TOKEN],
                    ['', "option value=" . Sage::TOKEN . " selected='selected'"],
                    $content
                );
            }
            echo $content;
        }, $screen, $context, 'high');
    }

    /**
     * woocommerce/src/Internal/Admin/Orders/Edit.php:78 add_meta_box('woocommerce-order-data'
     */
    public static function showMetaBoxOrder(array $wp_meta_boxes, string $screen): void
    {
        $id = 'woocommerce-order-data';
        $context = 'normal';
        remove_meta_box($id, $screen, $context);

        $callback = $wp_meta_boxes[$screen][$context]["high"][$id]["callback"];
        add_meta_box($id, sprintf(__('%s data', 'woocommerce'), __('Order', 'woocommerce')), static function (WC_Order $order) use ($callback): void {
            echo WoocommerceController::getMetaBoxOrder($order, $callback);
        }, $screen, $context, 'high');
    }

    public static function getMetaBoxOrder(WC_Order $order, ?callable $callback = null): string
    {
        ob_start();
        if (is_null($callback)) {
            WC_Meta_Box_Order_Data::output($order);
        } else {
            $callback($order);
        }
        $html = ob_get_clean();
        $crawler = new Crawler($html);
        $fDocenteteIdentifier = WoocommerceService::getInstance()->getFDocenteteIdentifierFromOrder($order);
        $translations = SageTranslationUtils::getTranslations();
        if (!empty($fDocenteteIdentifier)) {
            $a = $crawler->filter('.woocommerce-order-data__heading')->first();
            $title = $a->innerText();
            return str_replace($title, $title . ' ['
                . $translations["fDocentetes"]["doType"]["values"][$fDocenteteIdentifier["doType"]]
                . ': n° '
                . $fDocenteteIdentifier["doPiece"]
                . ']', $html);
        }
        return $html;
    }
}
