<?php

namespace App\lib;

use App\class\SageEntityMenu;
use App\enum\Sage\ArticleTypeEnum;
use App\enum\Sage\DocumentFraisTypeEnum;
use App\enum\Sage\DomaineTypeEnum;
use App\enum\Sage\ETypeCalculEnum;
use App\enum\Sage\NomenclatureTypeEnum;
use App\enum\Sage\TaxeTauxType;
use App\Sage;
use App\SageSettings;
use App\Utils\FDocenteteUtils;
use App\Utils\OrderUtils;
use App\Utils\PCatComptaUtils;
use App\Utils\RoundUtils;
use StdClass;
use WC_Cart;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Order_Item_Tax;
use WC_Product;
use WC_Product_Simple;
use WC_Shipping_Rate;
use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

final class SageWoocommerce
{
    private static ?self $_instance = null;
    private array $prices = [];

    private function __construct(public ?Sage $sage)
    {
        // region edit woocommerce price
        // https://stackoverflow.com/a/45807054/6824121
        add_filter('woocommerce_get_price_including_tax', function ($price, $quantity, $product) {
            return $this->custom_price($price, $product, get_current_user_id(), true);
        }, 99, 3);
        add_filter('woocommerce_get_price_excluding_tax', function ($price, $quantity, $product) {
            return $this->custom_price($price, $product, get_current_user_id(), false);
        }, 99, 3);
        // Simple, grouped and external products
        add_filter('woocommerce_product_get_price', fn($price, $product) => $this->custom_price($price, $product, get_current_user_id()), 99, 2);
        add_filter('woocommerce_product_get_regular_price', fn($price, $product) => $this->custom_price($price, $product, get_current_user_id()), 99, 2);
        // Variations
        add_filter('woocommerce_product_variation_get_regular_price', fn($price, $product) => $this->custom_price($price, $product, get_current_user_id()), 99, 2);
        add_filter('woocommerce_product_variation_get_price', fn($price, $product) => $this->custom_price($price, $product, get_current_user_id()), 99, 2);
        // Variable (price range)
//        add_filter('woocommerce_variation_prices_price', fn($price, $variation, $product) => $this->custom_variable_price($price, $variation, $product), 99, 3);
//        add_filter('woocommerce_variation_prices_regular_price', fn($price, $variation, $product) => $this->custom_variable_price($price, $variation, $product), 99, 3);
        // Handling price caching (see explanations at the end)
//        add_filter('woocommerce_get_variation_prices_hash', fn($price_hash, $product, $for_display) => $this->add_price_multiplier_to_variation_prices_hash($price_hash, $product, $for_display), 99, 3);
        // endregion

        // region edit woocommerce product display
        add_action('woocommerce_after_order_itemmeta', function (int $item_id, WC_Order_Item $item, WC_Product|bool|null $product) {
            if (
                is_bool($product) ||
                is_null($product) ||
                !($item instanceof WC_Order_Item_Product)
            ) {
                return;
            }
            /** @var WC_Meta_Data[] $metaDatas */
            $metaDatas = $product->get_meta_data();
            foreach ($metaDatas as $metaData) {
                $data = $metaData->get_data();
                if ($data["key"] === Sage::META_KEY_AR_REF) {
                    echo __('Sage ref', Sage::TOKEN) . ': ' . $data["value"];
                    break;
                }
            }
        }, 10, 3);
        // endregion

        // region add column to product list
        add_filter('manage_edit-product_columns', function (array $columns) { // https://stackoverflow.com/a/44702012/6824121
            $columns[Sage::TOKEN] = __('Sage', Sage::TOKEN); // todo change css class
            return $columns;
        }, 10, 1);

        add_action('manage_product_posts_custom_column', function (string $column, int $postId) { // https://www.conicsolutions.net/tutorials/woocommerce-how-to-add-custom-columns-on-the-products-list-in-dashboard/
            if ($column === Sage::TOKEN) {
                $arRef = Sage::getArRef($postId);
                if (!empty($arRef)) {
//                    echo '<span class="dashicons dashicons-yes" style="color: green"></span>';
                    echo $arRef;
                } else {
                    echo '<span class="dashicons dashicons-no" style="color: red"></span>';
                }
            }
        }, 10, 2);
        // endregion
    }

    private function custom_price(string $price, WC_Product $product, int $userId = 0, ?bool $withTaxes = null): float|string
    {
        $field = 'priceHt';
        if (
            $withTaxes === true ||
            (is_null($withTaxes) && get_option('woocommerce_tax_display_shop') !== 'excl') // excl || incl
        ) {
            $field = 'priceTtc';
        }
        $identifier = $product->get_id() . '_' . $userId . '_' . $field;
        if (array_key_exists($identifier, $this->prices)) { // performance
            return $this->prices[$identifier];
        }
        $arRef = $product->get_meta(Sage::META_KEY_AR_REF);
        if (empty($arRef)) {
            $this->prices[$identifier] = $price;
            return $this->prices[$identifier];
        }
        $prices = $this->getPricesProduct($product);
        if (empty($prices)) {
            return $price;
        }
        $flattenPrices = [];
        foreach ($prices as $price1) {
            foreach ($price1 as $price2) {
                $flattenPrices[] = $price2;
            }
        }
        $maxPrice = $this->getMaxPrice($flattenPrices);
        if ($userId === 0 || is_admin()) {
            $this->prices[$identifier] = $maxPrice->{$field};
            return $this->prices[$identifier];
        }
        $metadata = get_user_meta($userId);
        if (!isset(
            $metadata["_" . Sage::TOKEN . "_nCatTarif"][0],
            $metadata["_" . Sage::TOKEN . "_nCatCompta"][0]
        )) {
            $this->prices[$identifier] = $maxPrice->{$field};
            return $this->prices[$identifier];
        }
        $this->prices[$identifier] = $prices
        [$metadata["_" . Sage::TOKEN . "_nCatTarif"][0]]
        [$metadata["_" . Sage::TOKEN . "_nCatCompta"][0]]->{$field} ?? $maxPrice->{$field};
        return $this->prices[$identifier];
    }

    public function getPricesProduct(WC_Product $product, bool $flat = false): array
    {
        $r = [];
        /** @var WC_Meta_Data[] $metaDatas */
        $metaDatas = $product->get_meta_data();
        foreach ($metaDatas as $metaData) {
            $data = $metaData->get_data();
            if ($data["key"] !== '_' . Sage::TOKEN . '_prices') {
                continue;
            }
            $prices = json_decode($data["value"], false, 512, JSON_THROW_ON_ERROR);
            foreach ($prices as $price) {
                // Catégorie comptable (nCatCompta): [Locale, Export, Métropole]
                // Catégorie tarifaire (nCatTarif): [Tarif GC, Tarif Remise, Prix public, Tarif Partenaire]
                if ($flat) {
                    $r[] = $price;
                } else {
                    $r[$price->nCatTarif->cbIndice][$price->nCatCompta->cbIndice] = $price;
                }
            }
            break;
        }
        return $r;
    }

    public function getMaxPrice(array $prices): stdClass|null
    {
        if (count($prices) === 0) {
            return null;
        }
        usort($prices, static function (StdClass $a, StdClass $b) {
            return $b->priceTtc <=> $a->priceTtc;
        });
        return $prices[0];
    }

    public static function instance(Sage $sage): ?self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($sage);
        }

        return self::$_instance;
    }

    public function populateMetaDatas(?array $data, array $fields, SageEntityMenu $sageEntityMenu): array|null
    {
        if (empty($data)) {
            return $data;
        }
        $entityName = $sageEntityMenu->getEntityName();
        $fieldNames = array_map(static function (array $field) {
            return str_replace(SageSettings::PREFIX_META_DATA, '', $field['name']);
        }, array_filter($fields, static function (array $field) {
            return str_starts_with($field['name'], SageSettings::PREFIX_META_DATA);
        }));
        $mandatoryField = $sageEntityMenu->getMandatoryFields()[0];
        $getIdentifier = $sageEntityMenu->getGetIdentifier();
        if (is_null($getIdentifier)) {
            $getIdentifier = static function (array $entity) use ($mandatoryField) {
                return $entity[$mandatoryField];
            };
        }
        $ids = array_map($getIdentifier, $data["data"][$entityName]["items"]);

        $metaKeyIdentifier = $sageEntityMenu->getMetaKeyIdentifier();
        $metaTable = $sageEntityMenu->getMetaTable();
        $metaColumnIdentifier = $sageEntityMenu->getMetaColumnIdentifier();
        global $wpdb;
        $temps = $wpdb->get_results("
SELECT " . $metaTable . "2." . $metaColumnIdentifier . " post_id, " . $metaTable . "2.meta_value, " . $metaTable . "2.meta_key
FROM " . $metaTable . "
         LEFT JOIN " . $metaTable . " " . $metaTable . "2 ON " . $metaTable . "2." . $metaColumnIdentifier . " = " . $metaTable . "." . $metaColumnIdentifier . "
WHERE " . $metaTable . ".meta_value IN ('" . implode("','", $ids) . "')
  AND " . $metaTable . "2.meta_key IN ('" . implode("','", [$metaKeyIdentifier, ...$fieldNames]) . "')
ORDER BY " . $metaTable . "2.meta_key = '" . $metaKeyIdentifier . "' DESC;
");
        $results = [];
        $mapping = [];
        foreach ($temps as $temp) {
            if ($temp->meta_key === $metaKeyIdentifier) {
                $results[$temp->meta_value] = [];
                $mapping[$temp->post_id] = $temp->meta_value;
                continue;
            }
            $results[$mapping[$temp->post_id]][$temp->meta_key] = $temp->meta_value;
        }

        $includePostId = array_filter($fields, static function (array $field) {
                return $field['name'] === SageSettings::META_DATA_PREFIX . '_postId';
            }) !== [];
        $mapping = array_flip($mapping);
        $canImport = $sageEntityMenu->getCanImport();
        $postUrl = $sageEntityMenu->getPostUrl();
        if (is_null($postUrl)) {
            $postUrl = static function (array $entity) {
                if (!empty($entity["_" . Sage::TOKEN . "_postId"])) {
                    return admin_url('post.php?post=' . $entity["_" . Sage::TOKEN . "_postId"]) . '&action=edit';
                }
                return null;
            };
        }
        foreach ($data["data"][$entityName]["items"] as $i => &$item) {
            foreach ($fieldNames as $fieldName) {
                if (isset($results[$item[$mandatoryField]][$fieldName])) {
                    $item[$fieldName] = $results[$item[$mandatoryField]][$fieldName];
                } else {
                    $item[$fieldName] = '';
                }
            }
            if ($includePostId) {
                $item['_' . Sage::TOKEN . '_postId'] = null;
                $key = $getIdentifier($item);
                if (array_key_exists($key, $mapping)) {
                    $item['_' . Sage::TOKEN . '_postId'] = $mapping[$key];
                }
            }
            $item['_' . Sage::TOKEN . '_can_import'] = $canImport($item);
            $item['_' . Sage::TOKEN . '_post_url'] = $postUrl($item);
            $item['_' . Sage::TOKEN . '_identifier'] = $ids[$i];
        }
        return $data;
    }

    public function desynchronizeOrder(WC_Order $order): WC_Order
    {
        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        if (!empty($fDocenteteIdentifier)) {
            $order->add_order_note(__('Le document de vente Sage a été désynchronisé de la commande.', Sage::TOKEN) . ' [' . $fDocenteteIdentifier["doPiece"] . ']');
            $order->delete_meta_data(Sage::META_KEY_IDENTIFIER);
            $order->save();
        }
        return $order;
    }

    public function getFDocenteteIdentifierFromOrder(WC_Order $order): array|null
    {
        $fDocenteteIdentifier = null;
        foreach ($order->get_meta_data() as $meta) {
            $data = $meta->get_data();
            if ($data['key'] === Sage::META_KEY_IDENTIFIER) {
                $fDocenteteIdentifier = json_decode($data['value'], true, 512, JSON_THROW_ON_ERROR);
                break;
            }
        }
        return $fDocenteteIdentifier;
    }

    public function importFDocenteteFromSage(string $doPiece, string $doType, array $headers = []): array
    {
        $order = new WC_Order();
        $order = $this->linkOrderFDocentete($order, $doPiece, $doType, true, headers: $headers);
        $extendedFDocentetes = $this->sage->sageGraphQl->getFDocentetes(
            $doPiece,
            [(int)$doType],
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
        $tasksSynchronizeOrder = $this->getTasksSynchronizeOrder($order, $extendedFDocentetes);
        return $this->applyTasksSynchronizeOrder($order, $tasksSynchronizeOrder, $headers);
    }

    public function linkOrderFDocentete(WC_Order $order, string $doPiece, int $doType, bool $ignorePingApi, array $headers = []): WC_Order
    {
        $fDocentete = $this->sage->sageGraphQl->getFDocentetes(
            $doPiece,
            [$doType],
            doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
            doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
            ignorePingApi: $ignorePingApi,
            single: true
        );
        if ($fDocentete instanceof stdClass) {
            $order->update_meta_data(Sage::META_KEY_IDENTIFIER, json_encode([
                'doPiece' => $fDocentete->doPiece,
                'doType' => $fDocentete->doType,
            ], JSON_THROW_ON_ERROR));
            $order->save();
            $extendedFDocentetes = $this->sage->sageGraphQl->getFDocentetes(
                $doPiece,
                [$doType],
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
            $tasksSynchronizeOrder = $this->getTasksSynchronizeOrder($order, $extendedFDocentetes);
            [$message, $order] = $this->applyTasksSynchronizeOrder($order, $tasksSynchronizeOrder, headers: $headers);
        }
        return $order;
    }

    public function getTasksSynchronizeOrder(
        WC_Order          $order,
        array|null|string $extendedFDocentetes,
        bool              $allChanges = true,
        bool              $getProductChanges = false,
        bool              $getShippingChanges = false,
        bool              $getFeeChanges = false,
        bool              $getCouponChanges = false,
        bool              $getTaxesChanges = false,
        bool              $getUserChanges = false,
    ): array
    {
        $result = [
            'allProductsExistInWordpress' => true,
            'syncChanges' => [],
            'products' => [],
        ];
        if (empty($extendedFDocentetes) || is_string($extendedFDocentetes)) {
            return $result;
        }
        $taxeCodesProduct = [];
        $taxeCodesShipping = [];
        $getProductChanges = $allChanges || $getProductChanges;
        $getShippingChanges = $allChanges || $getShippingChanges;
        $getFeeChanges = $allChanges || $getFeeChanges;
        $getCouponChanges = $allChanges || $getCouponChanges;
        $getTaxesChanges = $allChanges || $getTaxesChanges;
        $getUserChanges = $allChanges || $getUserChanges;
        $fDoclignes = $this->getFDoclignes($extendedFDocentetes);
        $mainFDocentete = $this->getMainFDocenteteOfExtendedFDocentetes($order, $extendedFDocentetes);
        if ($getProductChanges || $getTaxesChanges) {
            [$productChanges, $products, $taxeCodesProduct] = $this->getTasksSynchronizeOrder_Products($order, $fDoclignes);
            $result['products'] = $products;
            if ($getProductChanges) {
                $result['syncChanges'] = [...$result['syncChanges'], ...$productChanges];
            }
        }
        if ($getShippingChanges || $getTaxesChanges) {
            [$shippingChanges, $taxeCodesShipping] = $this->getTasksSynchronizeOrder_Shipping($order, $mainFDocentete);
            if ($getShippingChanges) {
                $result['syncChanges'] = [...$result['syncChanges'], ...$shippingChanges];
            }
        }
        if ($getFeeChanges) {
            $feeChanges = $this->getTasksSynchronizeOrder_Fee($order);
            $result['syncChanges'] = [...$result['syncChanges'], ...$feeChanges];
        }
        if ($getCouponChanges) {
            $couponChanges = $this->getTasksSynchronizeOrder_Coupon($order);
            $result['syncChanges'] = [...$result['syncChanges'], ...$couponChanges];
        }
        if ($getTaxesChanges) {
            $taxeCodesProduct = array_values(array_unique([...$taxeCodesProduct, ...$taxeCodesShipping]));
            $taxesChanges = $this->getTasksSynchronizeOrder_Taxes($order, $taxeCodesProduct);
            $result['syncChanges'] = [...$result['syncChanges'], ...$taxesChanges];
        }
        if ($getUserChanges) {
            $userChanges = $this->getTasksSynchronizeOrder_User($order, $mainFDocentete);
            $result['syncChanges'] = [...$result['syncChanges'], ...$userChanges];
        }

        $result['allProductsExistInWordpress'] = array_filter($fDoclignes, static function (stdClass $fDocligne) {
                return is_null($fDocligne->postId);
            }) === [];

        return $result;
    }

    public function getFDoclignes(array|null|string $fDocentetes): array
    {
        if (!is_array($fDocentetes)) {
            return [];
        }
        $fDoclignes = [];
        foreach ($fDocentetes as $fDocentete) {
            $fDoclignes = [...$fDoclignes, ...$fDocentete->fDoclignes];
        }
        usort($fDoclignes, static function (stdClass $a, stdClass $b) {
            foreach (FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE as $suffix) {
                if ($a->{'dlPiece' . $suffix} !== $b->{'dlPiece' . $suffix}) {
                    return strcmp($a->{'dlPiece' . $suffix}, $b->{'dlPiece' . $suffix});
                }
            }
            if ($a->doType !== $b->doType) {
                return $a->doType <=> $b->doType;
            }
            return $a->doPiece <=> $b->doPiece;
        });
        return $fDoclignes;
    }

    public function getMainFDocenteteOfExtendedFDocentetes(WC_Order $order, array|null|string $extendedFDocentetes): stdClass|null|string
    {
        if (empty($extendedFDocentetes)) {
            return null;
        }
        if (!is_array($extendedFDocentetes)) {
            return $extendedFDocentetes;
        }
        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        if (count($extendedFDocentetes) > 1) {
            usort($extendedFDocentetes, static function (stdClass $a, stdClass $b) use ($fDocenteteIdentifier) {
                if ($fDocenteteIdentifier["doPiece"] === $a->doPiece && $fDocenteteIdentifier["doType"] === $a->doType) {
                    return -1;
                }
                if ($fDocenteteIdentifier["doPiece"] === $b->doPiece && $fDocenteteIdentifier["doType"] === $b->doType) {
                    return 1;
                }
                return $b->doType <=> $a->doType;
            });
        }
        return array_values($extendedFDocentetes)[0];
    }

    private function getTasksSynchronizeOrder_Products(WC_Order $order, array $fDoclignes): array
    {
        $taxeCodes = [];
        // to get order data: wp-content/plugins/woocommerce/includes/admin/meta-boxes/views/html-order-items.php:24
        $lineItems = array_values($order->get_items());

        $nbLines = max(count($lineItems), count($fDoclignes));
        $productChanges = [];
        [$taxe, $rates] = $this->sage->settings->getWordpressTaxes();
        for ($i = 0; $i < $nbLines; $i++) {
            $old = null;
            if (isset($lineItems[$i])) {
                $data = $lineItems[$i]->get_data();
                $old = new stdClass();
                $old->itemId = $data["id"];
                $old->postId = $data["product_id"];
                $old->quantity = $data["quantity"];
                $old->linePriceHt = (float)$data["total"];
                $old->taxes = [];
                $taxeNumber = 1;
                foreach ($data["taxes"]["total"] as $idRate => $amount) {
                    $old->taxes[$taxeNumber] = ['code' => $rates[$idRate]->tax_rate_name, 'amount' => (float)$amount];
                    $taxeNumber++;
                }
            }
            $new = null;
            if (isset($fDoclignes[$i])) {
                $new = new stdClass();
                $new->postId = $fDoclignes[$i]->postId;
                $new->arRef = $fDoclignes[$i]->arRef;
                $new->fDocligneLabel = $fDoclignes[$i]->dlDesign;
                $new->quantity = (int)$fDoclignes[$i]->dlQte;
                $new->linePriceHt = (float)$fDoclignes[$i]->dlMontantHt;
                $new->taxes = [];
                foreach (FDocenteteUtils::ALL_TAXES as $taxeNumber) {
                    $code = $fDoclignes[$i]->{'dlCodeTaxe' . $taxeNumber};
                    if (!is_null($code)) {
                        $taxeCodes[] = $fDoclignes[$i]->{'dlCodeTaxe' . $taxeNumber};
                        $new->taxes[$taxeNumber] = ['code' => $fDoclignes[$i]->{'dlCodeTaxe' . $taxeNumber}, 'amount' => (float)$fDoclignes[$i]->{'dlMontantTaxe' . $taxeNumber}];
                    }
                }
            }
            $changes = [];
            if (!is_null($new) && !is_null($old)) {
                if ($new->postId !== $old->postId) {
                    $changes[] = OrderUtils::REPLACE_PRODUCT_ACTION;
                } else {
                    if ($new->quantity !== $old->quantity) {
                        $changes[] = OrderUtils::CHANGE_QUANTITY_PRODUCT_ACTION;
                    }
                    if ($new->linePriceHt !== $old->linePriceHt) {
                        $changes[] = OrderUtils::CHANGE_PRICE_PRODUCT_ACTION;
                    }
                    if (array_values($new->taxes) !== array_values($old->taxes)) {
                        $changes[] = OrderUtils::CHANGE_TAXES_PRODUCT_ACTION;
                    }
                }
            } else if (is_null($new)) {
                $changes[] = OrderUtils::REMOVE_PRODUCT_ACTION;
            } else if (is_null($old) && !is_null($new->arRef)) {
                $changes[] = OrderUtils::ADD_PRODUCT_ACTION;
            }
            if (!empty($changes)) {
                $productChanges[$i] = [
                    'old' => $old,
                    'new' => $new,
                    'changes' => $changes,
                ];
            }
        }
        $productIds = [];
        foreach ($productChanges as $productChange) {
            $productIds[] = $productChange["old"]?->postId;
            $productIds[] = $productChange["new"]?->postId;
        }
        $productIds = array_values(array_filter(array_unique($productIds)));
        $products = [];
        if (!empty($productIds)) {
            $products = wc_get_products(['include' => $productIds]); // https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query
            $products = array_combine(array_map(static function (WC_Product $product) {
                return $product->get_id();
            }, $products), $products);
        }
        return [$productChanges, $products, $taxeCodes];
    }

    private function getTasksSynchronizeOrder_Shipping(WC_Order $order, stdClass $fDocentete): array
    {
        $taxeCodes = [];
        [$taxe, $rates] = $this->sage->settings->getWordpressTaxes();
        $pExpeditions = $this->sage->sageGraphQl->getPExpeditions(
            getError: true, // on admin page
        );
        if (Sage::showErrors($pExpeditions)) {
            return [];
        }
        $shippingChanges = [];

        // to get order data: wp-content/plugins/woocommerce/includes/admin/meta-boxes/views/html-order-items.php:27
        $lineItemsShipping = array_values($order->get_items('shipping'));

        $old = null;
        // region new
        $new = new stdClass();
        $new->method_id = FDocenteteUtils::slugifyPExpeditionEIntitule($fDocentete->doExpeditNavigation->eIntitule);
        $pExpedition = current(array_filter($pExpeditions, static function (stdClass $pExpedition) use ($new) {
            return $pExpedition->slug === $new->method_id;
        }));
        $new->name = '';
        if ($pExpedition !== false) {
            $new->name = $pExpedition->eIntitule;
        }
        $new->priceHt = RoundUtils::round($fDocentete->fraisExpedition->priceHt);
        $new->priceTtc = RoundUtils::round($fDocentete->fraisExpedition->priceTtc);
        $new->taxes = [];
        if (!is_null($fDocentete->fraisExpedition->taxes)) {
            foreach ($fDocentete->fraisExpedition->taxes as $taxe) {
                $taxeCodes[] = $taxe->fTaxe->taCode;
                $new->taxes[$taxe->taxeNumber] = ['code' => $taxe->fTaxe->taCode, 'amount' => (float)$taxe->amount];
            }
        }
        // endregion

        $foundSimilar = false;
        $formatFunction = function (stdClass $oldOrNew) {
            $oldOrNew->taxes = array_filter($oldOrNew->taxes, static function (array $taxe) {
                return $taxe['amount'] > 0;
            });
            usort($oldOrNew->taxes, static function (array $a, array $b) {
                return strcmp($a['code'], $b['code']);
            });
            $oldOrNew->taxes = array_values($oldOrNew->taxes);
            return $oldOrNew;
        };
        if (!empty($lineItemsShipping)) {
            foreach ($lineItemsShipping as $lineItemShipping) {
                $data = $lineItemShipping->get_data();
                $old = new stdClass();
                $old->method_id = $data["method_id"];
                $old->name = $data["method_title"];
                $old->priceHt = RoundUtils::round($data["total"]);
                $old->priceTtc = RoundUtils::round($old->priceHt + RoundUtils::round($data["total_tax"]));
                $old->taxes = [];
                if (!is_null($data["taxes"])) {
                    $taxeNumber = 1;
                    foreach ($data["taxes"]["total"] as $idRate => $amount) {
                        $old->taxes[$taxeNumber] = ['code' => $rates[$idRate]->tax_rate_name, 'amount' => (float)$amount];
                        $taxeNumber++;
                    }
                }
                if (
                    json_encode($formatFunction($old), JSON_THROW_ON_ERROR) ===
                    json_encode($formatFunction($new), JSON_THROW_ON_ERROR)
                    && !$foundSimilar
                ) {
                    $foundSimilar = true;
                } else {
                    $old->id = $lineItemShipping->get_id();
                    $shippingChanges[] = [
                        'old' => $old,
                        'new' => $new,
                        'changes' => [OrderUtils::REMOVE_SHIPPING_ACTION],
                    ];
                }
            }
        }
        if (!$foundSimilar) {
            $shippingChanges[] = [
                'old' => $old,
                'new' => $new,
                'changes' => [OrderUtils::ADD_SHIPPING_ACTION],
            ];
        }

        // todo faire un cron qui regarde si une commande a été modifié dans Sage mais pas dans wordpress -> mettre à jour la commande wordpress

        return [$shippingChanges, $taxeCodes];
    }

    private function getTasksSynchronizeOrder_Fee(WC_Order $order): array
    {
        $feeChanges = [];
        $lineItemsFee = array_values($order->get_items('fee'));
        foreach ($lineItemsFee as $lineItemFee) {
            $old = new stdClass();
            $old->id = $lineItemFee->get_id();
            $old->name = $lineItemFee->get_name();
            $new = null;
            $feeChanges[] = [
                'old' => $old,
                'new' => $new,
                'changes' => [
                    OrderUtils::REMOVE_FEE_ACTION,
                ],
            ];
        }
        return $feeChanges;
    }

    private function getTasksSynchronizeOrder_Coupon(WC_Order $order): array
    {
        $couponChanges = [];
        $coupons = $order->get_coupons();
        foreach ($coupons as $coupon) {
            $old = new stdClass();
            $old->id = $coupon->get_id();
            $old->name = $coupon->get_name();
            $new = null;
            $couponChanges[] = [
                'old' => $old,
                'new' => $new,
                'changes' => [
                    OrderUtils::REMOVE_COUPON_ACTION,
                ],
            ];
        }
        return $couponChanges;
    }

    private function getTasksSynchronizeOrder_Taxes(WC_Order $order, array $new): array
    {
        $taxesChanges = [];
        $old = array_values(array_map(static function (WC_Order_Item_Tax $wcOrderItemTax) {
            return $wcOrderItemTax->get_label();
        }, $order->get_taxes()));
        $changes = [];
        [$toRemove, $toAdd] = $this->getToRemoveToAddTaxes($order, $new);
        if (count($toRemove) > 0 || count($toAdd) > 0) {
            $changes[] = OrderUtils::UPDATE_WC_ORDER_ITEM_TAX_ACTION;
        }
        if (!empty($changes)) {
            $taxesChanges[] = [
                'old' => $old,
                'new' => $new,
                'changes' => $changes,
            ];
        }
        return $taxesChanges;
    }

    private function getToRemoveToAddTaxes(WC_Order $order, array $new): array
    {
        $current = array_values(array_map(static function (WC_Order_Item_Tax $wcOrderItemTax) {
            return $wcOrderItemTax->get_label();
        }, $order->get_taxes()));
        $toRemove = array_diff($current, $new);
        $toAdd = array_diff($new, $current);
        return [$toRemove, $toAdd];
    }

    private function getTasksSynchronizeOrder_User(WC_Order $order, stdClass $fDocentete): array
    {
        $userChanges = [];
        $orderUserId = $order->get_user_id();
        $ctNum = $fDocentete->doTiers;
        $expectedUserId = $this->sage->getUserIdWithCtNum($ctNum);
        if ($expectedUserId !== $orderUserId) {
            $old = new stdClass();
            $old->userId = $orderUserId;
            $new = new stdClass();
            $new->userId = $expectedUserId;
            $new->ctNum = $ctNum;
            $userChanges[] = [
                'old' => $old,
                'new' => $new,
                'changes' => [
                    OrderUtils::CHANGE_CUSTOMER_ACTION
                ],
            ];
        } else if (!is_null($orderUserId)) {
            $userChanges = [...$userChanges, ...$this->getUserChanges($orderUserId, $fDocentete->doTiersNavigation)];
            $userChanges = [...$userChanges, ...$this->getOrderAddressTypeChanges(
                $order,
                $fDocentete->doTiersNavigation,
                $fDocentete->cbLiNoNavigation
            )];
        }
        return $userChanges;
    }

    private function getUserChanges(int $userId, stdClass $fComptet): array
    {
        $userChanges = [];
        $userMetaWordpress = get_user_meta($userId);
        [$userId, $userFromSage, $metadata] = $this->convertSageUserToWoocommerce($fComptet, userId: $userId);
        if (!($userFromSage instanceof WP_User)) {
            return $userChanges;
        }
        foreach (OrderUtils::ALL_ADDRESS_TYPE as $addressType) {
            $old = new stdClass();
            $new = new stdClass();
            $fields = [];
            foreach ($userMetaWordpress as $key => $value) {
                if (str_starts_with($key, $addressType)) {
                    $fields[] = $key;
                }
            }
            foreach ($metadata as $key => $value) {
                if (str_starts_with($key, $addressType)) {
                    $fields[] = $key;
                }
            }
            $fields = array_values(array_unique($fields));
            foreach ($fields as $field) {
                if (
                    !array_key_exists($field, $userMetaWordpress) ||
                    $userMetaWordpress[$field][0] !== $metadata[$field]
                ) {
                    if (array_key_exists($field, $userMetaWordpress)) {
                        $old->{$field} = $userMetaWordpress[$field][0];
                    } else {
                        $old->{$field} = null;
                    }
                    $new->{$field} = $metadata[$field];
                }
            }
            if ((array)$new !== []) {
                $userChanges[] = [
                    'old' => $old,
                    'new' => $new,
                    'changes' => [
                        OrderUtils::CHANGE_USER_ACTION . '_' . $addressType
                    ],
                ];
            }
        }
        return $userChanges;
    }

    public function convertSageUserToWoocommerce(
        StdClass $fComptet,
        ?int     $userId = null,
    ): array|string
    {
        $email = explode(';', $fComptet->ctEmail)[0];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [null, "<div class='notice notice-error is-dismissible'>" . __("L'adresse email n'est pas au bon format [email: '" . $email . "']", Sage::TOKEN) . "</div>", null];
        }
        $mailExistsUserId = email_exists($email);
        if ($mailExistsUserId !== false && $mailExistsUserId !== $userId) {
            $ctNum = $this->sage->getUserWordpressIdForSage($mailExistsUserId);
            if (!empty($ctNum)) {
                return [null, "<div class='notice notice-error is-dismissible'>" . __('This email address [' . $email . '] is already registered for user id: ' . $mailExistsUserId . '.', 'woocommerce') . "</div>", null];
            }
            $userId = $mailExistsUserId;
            $this->sage->updateUserOrFComptet($ctNum, $userId, $fComptet);
        }
        $fComptetAddress = Sage::createAddressWithFComptet($fComptet);
        $address = [];
        $fPays = $this->sage->sageGraphQl->getFPays(false, ignorePingApi: true);
        foreach (OrderUtils::ALL_ADDRESS_TYPE as $addressType) {
            $thisAdress = current(array_filter($fComptet->fLivraisons, static function (StdClass $fLivraison) use ($addressType, $fComptetAddress) {
                if ($addressType === OrderUtils::BILLING_ADDRESS_TYPE) {
                    return $fLivraison->liAdresseFact === 1;
                }
                return $fLivraison->liPrincipal === 1;
            }));
            if ($thisAdress === false) {
                $thisAdress = $fComptetAddress;
            }
            $address[$addressType] = $thisAdress;
        }
        $meta = [];
        $sageEntityMenu = current(array_filter($this->sage->settings->sageEntityMenus,
            static fn(SageEntityMenu $sageEntityMenu) => $sageEntityMenu->getMetaKeyIdentifier() === Sage::META_KEY_CT_NUM
        ));
        foreach ($sageEntityMenu->getMetadata() as $metadata) {
            $value = $metadata->getValue();
            if (!is_null($value)) {
                $meta['_' . Sage::TOKEN . $metadata->getField()] = $value($fComptet);
            }
        }
        foreach (OrderUtils::ALL_ADDRESS_TYPE as $addressType) {
            $thisAddress = $address[$addressType];
            [$firstName, $lastName] = Sage::getFirstNameLastName(
                $thisAddress->liIntitule,
                $thisAddress->liContact
            );
            $fPay = current(array_filter($fPays, static fn(StdClass $fPay) => $fPay->paIntitule === $thisAddress->liPays));
            $meta = [
                ...$meta,
                // region woocommerce (got from: woocommerce/includes/class-wc-privacy-erasers.php)
                $addressType . '_first_name' => $firstName,
                $addressType . '_last_name' => $lastName,
                $addressType . '_company' => Sage::getName(intitule: $thisAddress->liIntitule, contact: $thisAddress->liContact),
                $addressType . '_address_1' => $thisAddress->liAdresse,
                $addressType . '_address_2' => $thisAddress->liComplement,
                $addressType . '_city' => $thisAddress->liVille,
                $addressType . '_postcode' => $thisAddress->liCodePostal,
                $addressType . '_state' => $thisAddress->liCodeRegion,
                $addressType . '_country' => $fPay !== false ? $fPay->paCode : $thisAddress->liPaysCode,
                $addressType . '_phone' => $thisAddress->liTelephone,
                $addressType . '_email' => $thisAddress->liEmail,
                // endregion
            ];
        }
        [$firstName, $lastName] = Sage::getFirstNameLastName(
            $fComptet->ctIntitule,
            $fComptet->ctContact
        );
        $wpUser = new WP_User($userId ?? 0);
        $wpUser->display_name = Sage::getName(intitule: $fComptet->ctIntitule, contact: $fComptet->ctContact);
        $wpUser->first_name = $firstName;
        $wpUser->last_name = $lastName;
        $wpUser->user_email = $email;

        if (is_null($userId)) {
            $wpUser->user_login = $this->sage->getAvailableUserName($fComptet->ctNum);
            $wpUser->user_pass = bin2hex(random_bytes(5));
        }

        return [$userId, $wpUser, $meta];
    }

    private function getOrderAddressTypeChanges(WC_Order $order, stdClass $fComptet, stdClass $fLivraison): array
    {
        $addressTypeChanges = [];
        $addressTypes = [
            OrderUtils::BILLING_ADDRESS_TYPE => ['obj' => $fComptet, 'prefix' => 'ct'],
            OrderUtils::SHIPPING_ADDRESS_TYPE => ['obj' => $fLivraison, 'prefix' => 'li'],
        ];
        foreach ($addressTypes as $type => $data) {
            $old = new stdClass();
            $new = new stdClass();
            $obj = $data['obj'];
            $prefix = $data['prefix'];
            [$firstName, $lastName] = Sage::getFirstNameLastName($obj->{$prefix . 'Contact'}, $obj->{$prefix . 'Intitule'});
            $obj->firstName = $firstName;
            $obj->lastName = $lastName;
            $fieldMaps = [
                "first_name" => "firstName",
                "last_name" => "lastName",
                "company" => "%pIntitule",
                "address_1" => "%pAdresse",
                "address_2" => "%pComplement",
                "city" => "%pVille",
                "postcode" => "%pCodePostal",
                "state" => "%pCodeRegion",
                "country" => "%pPaysCode",
                "phone" => "%pTelephone",
            ];
            if ($type === OrderUtils::BILLING_ADDRESS_TYPE) {
                $fieldMaps['email'] = "%pEmail";
            }
            foreach ($fieldMaps as $key1 => $key2) {
                $key2 = str_replace('%p', $prefix, $key2);
                $objValue = $obj->{$key2};
                if ($key1 === 'email') {
                    $objValue = Sage::getValidWordpressMail($objValue);
                }
                if (
                    ($oldValue = $order->{'get_' . $type . '_' . $key1}()) !== $objValue &&
                    (!empty($oldValue) || !empty($objValue))
                ) {
                    $old->{$key1} = $oldValue;
                    $new->{$key1} = $objValue;
                }
            }
            if ((array)$new !== []) {
                $addressTypeChanges[] = [
                    'old' => $old,
                    'new' => $new,
                    'changes' => [
                        OrderUtils::CHANGE_ORDER_ADDRESS_TYPE_ACTION . '_' . $type,
                    ],
                ];
            }
        }
        return $addressTypeChanges;
    }

    public function applyTasksSynchronizeOrder(WC_Order $order, array $tasksSynchronizeOrder, array $headers = []): array
    {
        $message = '';
        $syncChanges = $tasksSynchronizeOrder["syncChanges"];
        usort($syncChanges, static function (array $a, array $b) {
            $lastA = in_array(OrderUtils::UPDATE_WC_ORDER_ITEM_TAX_ACTION, $a['changes'], true);
            $lastB = in_array(OrderUtils::UPDATE_WC_ORDER_ITEM_TAX_ACTION, $b['changes'], true);
            if ($lastA && !$lastB) {
                return 1;
            }
            if (!$lastA && $lastB) {
                return -1;
            }
            return 0;
        });
        $alreadyAddedTaxes = [];

        // region create missing products
        foreach ($tasksSynchronizeOrder["syncChanges"] as $i => $syncChange) {
            foreach ($syncChange['changes'] as $change) {
                switch ($change) {
                    case OrderUtils::ADD_PRODUCT_ACTION:
                    case OrderUtils::REPLACE_PRODUCT_ACTION:
                        if (is_null($syncChange["new"]->postId)) {
                            [$response, $responseError, $message2, $postId] = $this->importFArticleFromSage(
                                $syncChange["new"]->arRef,
                                ignorePingApi: true,
                                headers: $headers,
                                ignoreCanImport: true,
                            );
                            $tasksSynchronizeOrder["syncChanges"][$i]["new"]->postId = $postId;
                            $message .= $message2;
                        }
                        break;
                }
            }
        }
        // endregion


        foreach ($tasksSynchronizeOrder["syncChanges"] as $syncChange) {
            foreach ($syncChange['changes'] as $change) {
                // todo use $order->add_order_note ?
                switch ($change) {
                    case OrderUtils::ADD_PRODUCT_ACTION:
                        $message .= $this->addProductToOrder($order, $syncChange["new"]->postId, $syncChange["new"]->quantity, $syncChange["new"], $alreadyAddedTaxes);
                        break;
                    case OrderUtils::CHANGE_PRICE_PRODUCT_ACTION:
                        $message .= $this->changePriceProductOrder($order, $syncChange["old"]->itemId, $syncChange["new"]->linePriceHt);
                        break;
                    case OrderUtils::CHANGE_TAXES_PRODUCT_ACTION:
                        $message .= $this->changeTaxesProductOrder($order, $syncChange["old"]->itemId, $syncChange["new"]->taxes, $alreadyAddedTaxes);
                        break;
                    case OrderUtils::REPLACE_PRODUCT_ACTION:
                        $message .= $this->replaceProductToOrder($order, $syncChange["old"]->itemId, $syncChange["new"]->postId, $syncChange["new"]->quantity, $syncChange["new"], $alreadyAddedTaxes);
                        break;
                    case OrderUtils::ADD_SHIPPING_ACTION:
                        $message .= $this->addShippingToOrder($order, $syncChange["new"], $alreadyAddedTaxes);
                        break;
                    case OrderUtils::REMOVE_SHIPPING_ACTION:
                        $message .= $this->removeShippingToOrder($order, $syncChange["old"]->id);
                        break;
                    case OrderUtils::UPDATE_WC_ORDER_ITEM_TAX_ACTION:
                        $message .= $this->updateWcOrderItemTaxToOrder($order, $syncChange["new"], $alreadyAddedTaxes);
                        break;
                    case OrderUtils::REMOVE_PRODUCT_ACTION:
                        $message .= $this->removeProductOrder($order, $syncChange["old"]->itemId);
                        break;
                    case OrderUtils::CHANGE_QUANTITY_PRODUCT_ACTION:
                        $message .= $this->changeQuantityProductOrder($order, $syncChange["old"]->itemId, $syncChange["new"]->quantity);
                        break;
                    case OrderUtils::REMOVE_FEE_ACTION:
                        $message .= $this->removeFeeOrder($order, $syncChange["old"]->id);
                        break;
                    case OrderUtils::REMOVE_COUPON_ACTION:
                        $message .= $this->removeCouponOrder($order, $syncChange["old"]->id);
                        break;
                    case OrderUtils::CHANGE_CUSTOMER_ACTION:
                        $message .= $this->changeCustomerOrder($order, $syncChange["new"]);
                        break;
                    case OrderUtils::CHANGE_USER_ACTION . '_' . OrderUtils::BILLING_ADDRESS_TYPE:
                    case OrderUtils::CHANGE_USER_ACTION . '_' . OrderUtils::SHIPPING_ADDRESS_TYPE:
                        $message .= $this->updateUserMetas($order, $syncChange["new"]);
                        break;
                    case OrderUtils::CHANGE_ORDER_ADDRESS_TYPE_ACTION . '_' . OrderUtils::BILLING_ADDRESS_TYPE:
                        $message .= $this->updateOrderMetas($order, $syncChange["new"], OrderUtils::BILLING_ADDRESS_TYPE);
                        break;
                    case OrderUtils::CHANGE_ORDER_ADDRESS_TYPE_ACTION . '_' . OrderUtils::SHIPPING_ADDRESS_TYPE:
                        $message .= $this->updateOrderMetas($order, $syncChange["new"], OrderUtils::SHIPPING_ADDRESS_TYPE);
                        break;
                    default:
                        $message .= "<div class='notice notice-error is-dismissible'>
                    <p>" . __('Aucune action défini pour', Sage::TOKEN) . ": " . print_r($syncChange['changes'], true) . "</p>
                    </div>";
                }
            }
        }

        $message .= $this->removeDuplicateWcOrderItemTaxToOrder($order);

        // region woocommerce/includes/admin/wc-admin-functions.php:455 function wc_save_order_items
        $order = new WC_Order($order->get_id()); // to refresh order with data in bdd
        $order->update_taxes();
        $order->calculate_totals(false);
        // endregion

        return [$message, $order];
    }

    public function importFArticleFromSage(
        string        $arRef,
        bool          $ignorePingApi =
        false, array  $headers = [],
        bool          $ignoreCanImport = false,
        stdClass|null $fArticle = null,
    ): array
    {
        if (is_null($fArticle)) {
            $fArticle = $this->sage->sageGraphQl->getFArticle($arRef, ignorePingApi: $ignorePingApi);
        }
        if (is_null($fArticle)) {
            return [null, null, "<div class='error'>
                        " . __("L'article n'a pas pu être importé", Sage::TOKEN) . "
                                </div>"];
        }
        if (!$ignoreCanImport) {
            $canImportFArticle = $this->canImportFArticle($fArticle);
            if (!empty($canImportFArticle)) {
                return [null, null, "<div class='error'>
                        " . implode(' ', $canImportFArticle) . "
                                </div>"];
            }
        }
        $articlePostId = $this->sage->sageWoocommerce->getWooCommerceIdArticle($arRef);
        $isCreation = is_null($articlePostId);
        $article = $this->sage->sageWoocommerce->convertSageArticleToWoocommerce($fArticle,
            current(array_filter($this->sage->settings->sageEntityMenus,
                static fn(SageEntityMenu $sageEntityMenu) => $sageEntityMenu->getMetaKeyIdentifier() === Sage::META_KEY_AR_REF
            )));
        $dismissNotice = "<button type='button' class='notice-dismiss " . Sage::TOKEN . "-notice-dismiss'><span class='screen-reader-text'>" . __('Dismiss this notice.') . "</span></button>";
        $urlArticle = "<strong><span style='display: block; clear: both;'><a href='" . get_admin_url() . "post.php?post=%id%&action=edit'>" . __("Voir l'article", Sage::TOKEN) . "</a></span></strong>";
        if ($isCreation) {
            // cannot create an article without request
            // ========================================
            // created with: (new WC_REST_Products_Controller())->create_item($request);
            // woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php : public function create_item( $request )
            // which extends
            // woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-products-controller.php
            [$response, $responseError] = $this->sage->createResource(
                '/wc/v3/products/' . $articlePostId,
                'POST',
                $article,
                Sage::META_KEY_AR_REF,
                $arRef,
                headers: $headers
            );
            $postId = null;
            if (is_string($responseError)) {
                $message = $responseError;
            } else if ($response["response"]["code"] === 200) {
                $body = json_decode($response["body"], false, 512, JSON_THROW_ON_ERROR);
                $urlArticle = str_replace('%id%', $body->id, $urlArticle);
                $postId = $body->id;
                $message = "<div class='notice notice-success is-dismissible'>
                    <p>" . __('Article mis à jour: ' . $body->name, Sage::TOKEN) . "</p>" . $urlArticle . "
                    $dismissNotice
                            </div>";
            } else if ($response["response"]["code"] === 201) {
                $body = json_decode($response["body"], false, 512, JSON_THROW_ON_ERROR);
                $urlArticle = str_replace('%id%', $body->id, $urlArticle);
                $postId = $body->id;
                $message = "<div class='notice notice-success is-dismissible'>
                    <p>" . __('Article créé: ' . $body->name, Sage::TOKEN) . "</p>" . $urlArticle . "
                    $dismissNotice
                            </div>";
            } else {
                $message = $response["body"];
            }
        } else {
            $product = wc_get_product($articlePostId);
            $product->read_meta_data(true);
            $oldMetadata = $product->get_meta_data();
            $allMetadataNames = array_map(static fn(array $meta) => $meta['key'], $article["meta_data"]);
            foreach ($oldMetadata as $old) {
                if (!in_array($old->key, $allMetadataNames, true)) {
                    $product->delete_meta_data($old->key);
                }
            }
            foreach ($article["meta_data"] as $meta) {
                $product->update_meta_data($meta['key'], $meta['value']);
            }
            $product->save();
            $response = ['response' => ['code' => 200]];
            $responseError = null;
            $urlArticle = str_replace('%id%', $articlePostId, $urlArticle);
            $postId = $articlePostId;
            $message = "<div class='notice notice-success is-dismissible'>
                    <p>" . __('Article mis à jour: ' . $article["name"], Sage::TOKEN) . "</p>" . $urlArticle . "
                    $dismissNotice
                            </div>";
        }
        return [$response, $responseError, $message, $postId];
    }

    public function canImportFArticle(stdClass $fArticle): array
    {
        // all fields here must be [IsProjected(false)]
        $result = [];
        if (
            $fArticle->arType !== ArticleTypeEnum::ArticleTypeStandard->value &&
            $fArticle->arType !== ArticleTypeEnum::ArticleTypeGamme->value
        ) {
            $result[] = __("Seuls les articles standard ou à gamme peuvent être importés.", Sage::TOKEN);
        }
        if ($fArticle->arNomencl !== NomenclatureTypeEnum::NomenclatureTypeAucun->value) {
            $result[] = __("Seuls les articles ayant une nomenclature Aucun peuvent être importés.", Sage::TOKEN);
        }
        if (!$fArticle->arPublie) {
            $result[] = __("Seuls les articles publiés sur le site marchand peuvent être importés.", Sage::TOKEN);
        }
        return $result;
    }

    public function getWooCommerceIdArticle(string $arRef): int|null
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare(
                "
SELECT {$wpdb->posts}.ID
FROM {$wpdb->posts}
         INNER JOIN {$wpdb->postmeta} ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID AND {$wpdb->posts}.post_status != 'trash'
WHERE {$wpdb->posts}.post_type = 'product'
  AND {$wpdb->postmeta}.meta_key = %s
  AND {$wpdb->postmeta}.meta_value = %s
", [Sage::META_KEY_AR_REF, $arRef]));
        if (!empty($r)) {
            return (int)$r[0]->ID;
        }
        return null;
    }

    public function convertSageArticleToWoocommerce(StdClass $fArticle, SageEntityMenu $sageEntityMenu): array
    {
        $result = [
            'name' => $fArticle->arDesign,
            'meta_data' => [],
        ];
        foreach ($sageEntityMenu->getMetadata($fArticle) as $metadata) {
            $value = $metadata->getValue();
            if (!is_null($value)) {
                $v = $value($fArticle);
                if (is_bool($v)) {
                    $v = (int)$v;
                }
                $result['meta_data'][] = [
                    'key' => '_' . Sage::TOKEN . $metadata->getField(),
                    'value' => $v,
                ];
            }
        }
        return $result;
    }

    private function addProductToOrder(WC_Order $order, ?int $productId, int $quantity, stdClass $new, array &$alreadyAddedTaxes): string
    {
        $message = '';
        $qty = wc_stock_amount($quantity);
        if (is_null($new->postId)) {
            [$response, $responseError, $message2, $postId] = $this->importFArticleFromSage($new->arRef, ignorePingApi: true);
            if ($response["response"]["code"] !== 201) {
                return $message2;
            }
            $productId = json_decode($response["body"], false, 512, JSON_THROW_ON_ERROR)->id;
        }

        $product = wc_get_product($productId);
        $itemId = $order->add_product($product, $qty);
        $message .= $this->updateProductOrder($order, $itemId, $new, $alreadyAddedTaxes);
        return $message;
    }

    private function updateProductOrder(WC_Order $order, int $itemId, stdClass $new, array &$alreadyAddedTaxes): string
    {
        $message = $this->changeQuantityProductOrder($order, $itemId, $new->quantity, false);
        $message .= $this->changePriceProductOrder($order, $itemId, $new->linePriceHt, false);
        $message .= $this->changeTaxesProductOrder($order, $itemId, $new->taxes, $alreadyAddedTaxes);
        return $message;
    }

    private function changeQuantityProductOrder(WC_Order $order, int $itemId, int $quantity, bool $save = true): string
    {
        $message = '';
        $lineItems = array_values($order->get_items());

        foreach ($lineItems as $lineItem) {
            if ($lineItem->get_id() === $itemId) {
                $lineItem->set_quantity($quantity);
                if ($save) {
                    $lineItem->save();
                }
                break;
            }
        }
        return $message;
    }

    private function changePriceProductOrder(WC_Order $order, int $itemId, float $linePriceHt, bool $save = true): string
    {
        $lineItems = array_values($order->get_items());
        foreach ($lineItems as $lineItem) {
            if ($lineItem->get_id() === $itemId) {
                $lineItem->set_props([
                    'subtotal' => (string)$linePriceHt, // subtotal is what the price should be, if higher than total difference will be display as discount (Before discount)
                    'total' => (string)$linePriceHt,
                ]);
                if ($save) {
                    $lineItem->save();
                }
                break;
            }
        }
        return '';
    }

    private function changeTaxesProductOrder(WC_Order $order, int $itemId, array $taxes, array &$alreadyAddedTaxes, bool $save = true): string
    {
        $message = '';
        $lineItems = array_values($order->get_items());

        foreach ($lineItems as $lineItem) {
            if ($lineItem->get_id() === $itemId) {
                foreach ($taxes as $taxe) {
                    $alreadyAddedTaxes[] = $taxe['code'];
                }
                $lineItem->set_taxes($this->formatTaxes($order, $taxes, $message));
                if ($save) {
                    $lineItem->save();
                }
                break;
            }
        }
        return $message;
    }

    private function formatTaxes(WC_Order $order, array $taxes, string &$message, int $errorMissingTax = 0): array
    {
        $orderId = $order->get_id();
        $orderItemTaxes = $order->get_taxes();
        $orderItemTaxesRateId = array_map(static function (WC_Order_Item_Tax $orderItemTax) {
            return $orderItemTax->get_rate_id();
        }, $orderItemTaxes);
        [$taxe, $rates] = $this->sage->settings->getWordpressTaxes();
        $result = ['total' => [], 'subtotal' => []];
        foreach ($taxes as $taxe) {
            $rate = current(array_filter($rates, static function (stdClass $rate) use ($taxe) {
                return $rate->tax_rate_name === $taxe['code'];
            }));
            if ($rate === false) {
                if ($errorMissingTax === 0) {
                    $errorMissingTax++;
                    $this->sage->settings->updateTaxes();
                    return $this->formatTaxes($order, $taxes, $message, $errorMissingTax);
                }
                $message .= "<div class='notice notice-error is-dismissible'>
                    <p>" . __('Il semblerait que la taxe ' . $taxe['code'] . ' soit manquante.', Sage::TOKEN) . "</p>
                    </div>";
                continue;
            }
            $result['total'][$rate->tax_rate_id] = (string)$taxe['amount'];
            $result['subtotal'][$rate->tax_rate_id] = (string)$taxe['amount'];

            if (!in_array((int)$rate->tax_rate_id, $orderItemTaxesRateId, true)) {
                // woocommerce/includes/class-wc-ajax.php public static function add_order_tax
                $orderItemTax = new WC_Order_Item_Tax();
                $orderItemTax->set_rate($rate->tax_rate_id);
                $orderItemTax->set_order_id($orderId);
                $orderItemTax->save();
            }
        }
        return $result;
    }

    private function replaceProductToOrder(WC_Order $order, int $itemId, int $productId, int $quantity, stdClass $new, array &$alreadyAddedTaxes): string
    {
        $message = '';
        $lineItems = array_values($order->get_items());
        foreach ($lineItems as $lineItem) {
            if ($lineItem->get_id() === $itemId) {
                foreach ($new->taxes as $taxe) {
                    $alreadyAddedTaxes[] = $taxe['code'];
                }
                $lineItem->set_product(new WC_Product($productId));
                $message .= $this->updateProductOrder($order, $itemId, $new, $alreadyAddedTaxes);
                break;
            }
        }

        return $message;
    }

    private function addShippingToOrder(WC_Order $order, stdClass $new, array &$alreadyAddedTaxes): string
    {
        $message = '';
        foreach ($new->taxes as $taxe) {
            $alreadyAddedTaxes[] = $taxe['code'];
        }
        $item = new WC_Order_Item_Shipping();
        $item->set_props(array(
            'method_title' => $new->name,
            'method_id' => $new->method_id,
            'total' => wc_format_decimal($new->priceHt),
            'taxes' => $this->formatTaxes($order, $new->taxes, $message),
        ));
        $order->add_item($item);
        $order->save();
        return $message;
    }

    private function removeShippingToOrder(WC_Order $order, int $id): string
    {
        $message = '';
        $lineItemsShipping = array_values($order->get_items('shipping'));
        if (!empty($lineItemsShipping)) {
            foreach ($lineItemsShipping as $lineItemShipping) {
                if ($lineItemShipping->get_id() === $id) {
                    $order->remove_item($id);
                    $order->save();
                    break;
                }
            }
        }
        return $message;
    }

    private function updateWcOrderItemTaxToOrder(WC_Order $order, array $new, array $alreadyAddedTaxes): string
    {
        $message = '';
        $orderId = $order->get_id();
        [$toRemove, $toAdd] = $this->getToRemoveToAddTaxes($order, $new);
        $toAdd = array_diff($toAdd, $alreadyAddedTaxes);
        [$taxe, $rates] = $this->sage->settings->getWordpressTaxes();
        foreach ($toAdd as $codeToAdd) {
            $rate = current(array_filter($rates, static function (stdClass $rate) use ($codeToAdd) {
                return $rate->tax_rate_name === $codeToAdd;
            }));
            $orderItemTax = new WC_Order_Item_Tax();
            $orderItemTax->set_rate($rate->tax_rate_id);
            $orderItemTax->set_order_id($orderId);
            $orderItemTax->save();
        }
        if (!empty($toRemove)) {
            $wcOrderItemTaxs = $order->get_taxes();
            $wcShippingItemTaxs = $order->get_shipping_methods();
            foreach ($toRemove as $codeRemove) {
                foreach ($wcOrderItemTaxs as $wcOrderItemTax) {
                    if ($wcOrderItemTax->get_label() === $codeRemove) {
                        $wcOrderItemTax->delete();
                        // no break because can have multiple same label
                    }
                }
                foreach ($wcShippingItemTaxs as $wcShippingItemTax) {
                    $taxes = $wcShippingItemTax->get_taxes();
                    if (empty($taxes)) {
                        continue;
                    }
                    $keys = array_keys($taxes["total"]);
                    foreach ($keys as $key) {
                        if (in_array($rates[$key]->tax_rate_name, $toRemove, true)) {
                            unset($taxes["total"][$key]);
                        }
                    }
                    $wcShippingItemTax->set_taxes($taxes);
                }
            }
        }
        return $message;
    }

    private function removeProductOrder(WC_Order $order, int $itemId): string
    {
        $message = '';
        $lineItems = $order->get_items();

        $order->remove_item($itemId);
        foreach ($lineItems as $lineItem) {
            if ($lineItem->get_id() === $itemId) {
                $lineItem->delete();
                break;
            }
        }
        $order->save();
        return $message;
    }

    private function removeFeeOrder(WC_Order $order, int $id): string
    {
        $message = '';
        $lineItemsFee = array_values($order->get_items('fee'));
        foreach ($lineItemsFee as $lineItemFee) {
            if ($lineItemFee->get_id() === $id) {
                $lineItemFee->delete();
            }
        }
        return $message;
    }

    private function removeCouponOrder(WC_Order $order, int $id): string
    {
        $message = '';
        $coupons = $order->get_coupons();
        foreach ($coupons as $coupon) {
            if ($coupon->get_id() === $id) {
                $coupon->delete();
            }
        }
        return $message;
    }

    private function changeCustomerOrder(WC_Order $order, stdClass $new): string
    {
        $message = '';
        $userId = $new->userId;
        if (is_null($userId)) {
            [$userId, $message] = $this->sage->updateUserOrFComptet($new->ctNum, ignorePingApi: true);
            if (!is_numeric($userId)) {
                return $message;
            }
        }
        $order->set_customer_id($userId);
        $order->save();

        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        $extendedFDocentetes = $this->sage->sageGraphQl->getFDocentetes(
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

        if (!is_string($extendedFDocentetes)) {
            $this->applyTasksSynchronizeOrder($order, $this->getTasksSynchronizeOrder(
                $order,
                $extendedFDocentetes,
                allChanges: false,
                getUserChanges: true,
            ));
        } else {
            $message .= $extendedFDocentetes;
        }

        return $message;
    }

    private function updateUserMetas(WC_Order $order, stdClass $new): string
    {
        $message = '';
        $userId = $order->get_user_id();
        foreach ((array)$new as $key => $value) {
            update_user_meta($userId, $key, $value);
        }
        return $message;
    }

    private function updateOrderMetas(WC_Order $order, stdClass $new, string $addressType): string
    {
        $message = '';
        foreach ((array)$new as $key => $value) {
            if ($key === 'email') {
                $value = Sage::getValidWordpressMail($value);
            }
            $order->{'set_' . $addressType . '_' . $key}($value);
        }
        $order->save();
        return $message;
    }

    private function removeDuplicateWcOrderItemTaxToOrder(WC_Order $order): string
    {
        $message = '';
        $wcOrderItemTaxs = array_values($order->get_taxes());
        foreach ($wcOrderItemTaxs as $i => $wcOrderItemTax) {
            $hasDuplicate = false;
            for ($y = $i + 1, $yMax = count($wcOrderItemTaxs); $y < $yMax; $y++) {
                if ($wcOrderItemTax->get_label() === $wcOrderItemTaxs[$y]->get_label()) {
                    $hasDuplicate = true;
                    break;
                }
            }
            if ($hasDuplicate) {
                $wcOrderItemTax->delete();
            }
        }
        return $message;
    }

    public function getMetaboxSage(WC_Order $order, bool $ignorePingApi = false, string $message = ''): string
    {
        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        $hasFDocentete = !is_null($fDocenteteIdentifier);
        $extendedFDocentetes = null;
        $tasksSynchronizeOrder = [];
        if ($hasFDocentete) {
            $extendedFDocentetes = $this->sage->sageGraphQl->getFDocentetes(
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
            $tasksSynchronizeOrder = $this->getTasksSynchronizeOrder($order, $extendedFDocentetes);
        }
        // original WC_Meta_Box_Order_Data::output
        $pCattarifs = $this->sage->sageGraphQl->getPCattarifs();
        $pCatComptas = $this->sage->sageGraphQl->getPCatComptas();
        return $this->sage->twig->render('woocommerce/metaBoxes/main.html.twig', [
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

    public function importOrderFromSage(
        string                     $doPiece,
        int                        $doType,
        ?int                       $shouldBeOrderId = null,
        stdClass|null|false|string $fDocentete = null,
        bool                       $ignorePingApi = false
    ): array
    {
        if (is_null($fDocentete)) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $fDocentete = $this->sage->sageGraphQl->getFDocentetes(
                $doPiece,
                [$doType],
                doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
                doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
                getError: true,
                ignorePingApi: $ignorePingApi,
                getFDoclignes: true,
                getExpedition: true,
                addWordpressProductId: true,
                getUser: true,
                getLivraison: true,
                single: true,
            );
        }
        if (is_null($fDocentete) || $fDocentete === false) {
            return [null, "<div class='error'>
                        " . __("Le document de vente Sage n'a pas pu être importé", Sage::TOKEN) . "
                                </div>"];
        }
        $canImportFDocentete = $this->canImportOrderFromSage($fDocentete);
        if (!empty($canImportFDocentete)) {
            return [null, null, "<div class='error'>
                        " . implode(' ', $canImportFDocentete) . "
                                </div>"];
        }
        $orderId = $this->getOrderIdWithDoPieceDoType($doPiece, $doType);
        if (!is_null($shouldBeOrderId)) {
            if (is_null($orderId)) {
                $orderId = $shouldBeOrderId;
            } else if ($orderId !== $shouldBeOrderId) {
                return [null, "<div class='error'>
                        " . __("Ce document de vente Sage est déjà assigné à une commande Woocommerce", Sage::TOKEN) . "
                                </div>"];
            }
        }
        $newOrder = false;
        if (is_null($orderId)) {
            $newOrder = true;
            $order = wc_create_order();
            if ($order instanceof WP_Error) {
                return [null, "<div class='notice notice-error is-dismissible'>
                                <pre>" . $order->get_error_code() . "</pre>
                                <pre>" . $order->get_error_message() . "</pre>
                                </div>"];
            }
            $order->add_order_note(__('Le document Sage a été synchronisé avec la commande.', Sage::TOKEN) . 'doPiece:[' . $fDocentete->doPiece . '] doType:[' . $fDocentete->doType . '].');
            $order->update_meta_data(Sage::META_KEY_IDENTIFIER, json_encode([
                'doPiece' => $fDocentete->doPiece,
                'doType' => $fDocentete->doType,
            ], JSON_THROW_ON_ERROR));
            $order->save();
            $orderId = $order->get_id();
        } else {
            $order = wc_get_order($orderId);
        }
        $extendedFDocentetes = $this->sage->sageGraphQl->getFDocentetes(
            $doPiece,
            [$doType],
            doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
            doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
            getError: true,
            ignorePingApi: $ignorePingApi,
            getFDoclignes: true,
            getExpedition: true,
            addWordpressProductId: true,
            getUser: true,
            getLivraison: true,
            extended: true,
        );
        [$message, $order] = $this->applyTasksSynchronizeOrder($order, $this->getTasksSynchronizeOrder($order, $extendedFDocentetes));

        $url = "<strong><span style='display: block; clear: both;'><a href='" . get_admin_url() . "admin.php?page=wc-orders&action=edit&id=" . $orderId . "'>" . __("Voir la commande", Sage::TOKEN) . "</a></span></strong>";
        if (!$newOrder) {
            return [$orderId, $message . "<div class='notice notice-success is-dismissible'>
                        " . __('La commande a été mise à jour', Sage::TOKEN) . $url . "
                                </div>"];
        }
        return [$orderId, $message . "<div class='notice notice-success is-dismissible'>
                        " . __('La commande a été créée', Sage::TOKEN) . $url . "
                                </div>"];
    }

    public function canImportOrderFromSage(stdClass $fDocentete): array
    {
        // all fields here must be [IsProjected(false)]
        $result = [];
        if ($fDocentete->doDomaine !== DomaineTypeEnum::DomaineTypeVente->value) {
            $result[] = __("Seuls les documents de ventes peuvent être importés.", Sage::TOKEN);
        }
        return $result;
    }

    public function getOrderIdWithDoPieceDoType(string $doPiece, int $doType): int|null
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare("
SELECT order_id
FROM " . $wpdb->prefix . "wc_orders_meta
WHERE meta_key = %s
  AND meta_value = %s
", [Sage::META_KEY_IDENTIFIER, json_encode(['doPiece' => $doPiece, 'doType' => $doType], JSON_THROW_ON_ERROR)]));
        if (!empty($r)) {
            return (int)$r[0]->order_id;
        }
        return null;
    }

    public function getShippingRateCosts(WC_Cart $wcCart, WC_Shipping_Rate $wcShippingRate): float|null
    {
        $pExpeditions = $this->sage->sageGraphQl->getPExpeditions();
        if (!is_array($pExpeditions)) {
            return null;
        }
        $methodId = $wcShippingRate->get_method_id();
        $pExpedition = current(array_filter($pExpeditions, static function ($pExpedition) use ($methodId) {
            return $pExpedition->slug === $methodId;
        }));
        if ($pExpedition === false) {
            return null;
        }
        $customer = $wcCart->get_customer();
        $userMetaWordpress = get_user_meta($customer->get_id(), single: true);
        $userNCatTarif = null;
        $userNCatCompta = null;
        if (isset($userMetaWordpress["_" . Sage::TOKEN . "_nCatTarif"][0])) {
            $userNCatTarif = (int)$userMetaWordpress["_" . Sage::TOKEN . "_nCatTarif"][0];
        }
        if (isset($userMetaWordpress["_" . Sage::TOKEN . "_nCatCompta"][0])) {
            $userNCatCompta = (int)$userMetaWordpress["_" . Sage::TOKEN . "_nCatCompta"][0];
        }
        $price = false;
        if (!is_null($pExpedition->arRefNavigation)) {
            $price = current(array_filter($pExpedition->arRefNavigation->prices, static function (stdClass $price) use ($userNCatTarif, $userNCatCompta) {
                return $price->nCatTarif->cbIndice === $userNCatTarif && $price->nCatCompta->cbIndice === $userNCatCompta;
            }));
        }
        $result = null;
        $woocommerceShowTax = get_option('woocommerce_tax_display_cart') !== "excl"; // excl || incl
        if ($pExpedition->eTypeCalcul === ETypeCalculEnum::Valeur->value) {
            $result = $pExpedition->eValFrais;
        } else {
            // grille, in this case (DocFraisTypeForfait && DocFraisTypeColisage) cannot be selected in sage
            if ($pExpedition->eTypeFrais === DocumentFraisTypeEnum::DocFraisTypeQuantite->value) {
                $quantity = 0;
                foreach ($wcCart->get_cart_contents() as $cartContent) {
                    $quantity += $cartContent["quantity"];
                }
                $result = $this->findFraisExpeditionGrille($pExpedition, $quantity);
            } else {
                $prop = '';
                if ($pExpedition->eTypeFrais === DocumentFraisTypeEnum::DocFraisTypePoidsNet->value) {
                    $prop = '_poids_net';
                } else if ($pExpedition->eTypeFrais === DocumentFraisTypeEnum::DocFraisTypePoidsBrut->value) {
                    $prop = '_poids_brut';
                }
                foreach ($wcCart->get_cart_contents() as $cartContent) {
                    /** @var WC_Product_Simple $product */
                    $product = $cartContent['data'];
                    /** @var WC_Meta_Data[] $metaDatas */
                    $metaDatas = $product->get_meta_data();
                    foreach ($metaDatas as $metaData) {
                        $data = $metaData->get_data();
                        if ($data["key"] === '_' . Sage::TOKEN . $prop) {
                            $result = $this->findFraisExpeditionGrille($pExpedition, (float)$metaData->get_data()['value']);
                            break;
                        }
                    }
                }
            }
        }
        $isTtc = (bool)$pExpedition->eTypeLigneFrais;
        if ($price !== false) {
            if ($woocommerceShowTax && !$isTtc) {
                $result = $this->applyTaxes($result, $price, true);
            } elseif (!$woocommerceShowTax && $isTtc) {
                $result = $this->applyTaxes($result, $price, false);
            }
        }
        return $result;
    }

    private function findFraisExpeditionGrille(stdClass $pExpedition, float $borne): float
    {
        $frais = 0;
        $lastBorne = 0;
        foreach ($pExpedition->fExpeditiongrilles as $fExpeditiongrille) {
            if ($fExpeditiongrille->egBorne > $lastBorne && $borne <= $fExpeditiongrille->egBorne) {
                $lastBorne = $fExpeditiongrille->egBorne;
                $frais = $fExpeditiongrille->egFrais;
            }
        }
        return $frais;
    }

    /**
     * Copy paste of applyTaxes of the sage api
     */
    private function applyTaxes(float $value, stdClass $price, bool $addOrRemove): float|null
    {
        $initPrice = $value;
        foreach ($price->taxes as $taxe) {
            if ($taxe->fTaxe->taNp !== 0) {
                continue;
            }
            if ($taxe->fTaxe->taTtaux === TaxeTauxType::TaxeTauxTypePourcent->value) {
                if ($addOrRemove) {
                    $amount = round(($initPrice * $taxe->fTaxe->taTaux)) / 100;
                } else {
                    $amount = (round($initPrice / (100 + $taxe->fTaxe->taTaux)) * 100) - $initPrice;
                }
            } else {
                $amount = $taxe->fTaxe->taTaux;
                if (!$addOrRemove) {
                    $amount = -$amount;
                }
            }
            $value += $amount;
        }
        return $value;
    }
}
