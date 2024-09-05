<?php

namespace App\lib;

use App\class\SageEntityMenu;
use App\Sage;
use App\SageSettings;
use App\Utils\FDocenteteUtils;
use App\Utils\OrderUtils;
use App\Utils\RoundUtils;
use StdClass;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Tax;
use WC_Product;
use WC_Shipping_Rate;

if (!defined('ABSPATH')) {
    exit;
}

final class SageWoocommerce
{
    private static ?self $_instance = null;

    private function __construct(public ?Sage $sage)
    {
        // region edit woocommerce price
        // https://stackoverflow.com/a/45807054/6824121
        // Simple, grouped and external products
        add_filter('woocommerce_product_get_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        add_filter('woocommerce_product_get_regular_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        // Variations
        add_filter('woocommerce_product_variation_get_regular_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
        add_filter('woocommerce_product_variation_get_price', fn($price, $product) => $this->custom_price($price, $product), 99, 2);
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
                    echo __('Sage ref', 'sage') . ': ' . $data["value"];
                    break;
                }
            }
        }, 10, 3);
        // endregion

        // region add column to product list
        add_filter('manage_edit-product_columns', function (array $columns) { // https://stackoverflow.com/a/44702012/6824121
            $columns['sage'] = __('Sage', 'sage'); // todo change css class
            return $columns;
        }, 10, 1);

        add_action('manage_product_posts_custom_column', function (string $column, int $postId) { // https://www.conicsolutions.net/tutorials/woocommerce-how-to-add-custom-columns-on-the-products-list-in-dashboard/
            if ($column === 'sage') {
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

    private function custom_price(string $price, WC_Product $product): float|string
    {
        $arRef = $product->get_meta(Sage::META_KEY_AR_REF);
        if (empty($arRef)) {
            return $price;
        }
        /** @var WC_Meta_Data[] $metaDatas */
        $metaDatas = $product->get_meta_data();
        $pCattarifs = $this->sage->sageGraphQl->getPCattarifs();
        foreach ($metaDatas as $metaData) {
            $data = $metaData->get_data();
            if ($data["key"] !== '_' . Sage::TOKEN . '_prices') {
                continue;
            }
            $pricesData = json_decode($data["value"], true, 512, JSON_THROW_ON_ERROR);
            $nCatTarifCbIndice = get_user_meta(get_current_user_id(), '_' . Sage::TOKEN . '_nCatTarif', true);
            if ($nCatTarifCbIndice !== '') {
                $nCatTarifCbMarq = current(array_filter($pCattarifs, static fn(StdClass $pCattarif) => $pCattarif->cbIndice === (int)$nCatTarifCbIndice));
                if ($nCatTarifCbMarq !== false) {
                    $priceData = current(array_filter($pricesData, static fn(array $p) => $p['nCatTarif'] === $nCatTarifCbMarq->cbMarq));
                    if ($priceData !== false) {
                        $price = $priceData["priceTtc"];
                    }
                }
            }
            if (empty($price)) {
                $allPrices = array_map(static function (array $priceData) {
                    return $priceData['priceTtc'];
                }, $pricesData);
                $price = max($allPrices);
            }
            break;
        }
        return (float)$price;
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
                return $field['name'] === SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_postId';
            }) !== [];
        $mapping = array_flip($mapping);
        foreach ($data["data"][$entityName]["items"] as &$item) {
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
        }
        return $data;
    }

    public function getMetaboxSage(WC_Order $order, bool $ignorePingApi = false, string $message = ''): string
    {
        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        $hasFDocentete = !is_null($fDocenteteIdentifier);
        $fDocentete = null;
        $tasksSynchronizeOrder = [];
        if ($hasFDocentete) {
            $fDocentete = $this->sage->sageGraphQl->getFDocentete(
                $fDocenteteIdentifier["doPiece"],
                $fDocenteteIdentifier["doType"],
                getError: true,
                getFDoclignes: true,
                getExpedition: true,
                ignorePingApi: $ignorePingApi,
                addWordpressProductId: true,
                getUser: true,
                getLivraison: true,
            );
            if (is_string($fDocentete) || is_bool($fDocentete)) {
                if (is_string($fDocentete)) {
                    $message .= $fDocentete;
                }
                $fDocentete = null;
            }
            $tasksSynchronizeOrder = $this->getTasksSynchronizeOrder($order, $fDocentete);
        }
        // original WC_Meta_Box_Order_Data::output
        return $this->sage->twig->render('woocommerce/metaBoxes/main.html.twig', [
            'message' => $message,
            'doPieceIdentifier' => $fDocenteteIdentifier ? $fDocenteteIdentifier["doPiece"] : null,
            'doTypeIdentifier' => $fDocenteteIdentifier ? $fDocenteteIdentifier["doType"] : null,
            'order' => $order,
            'hasFDocentete' => $hasFDocentete,
            'fDocentete' => $fDocentete,
            'currency' => get_woocommerce_currency(),
            'fdocligneMappingDoType' => FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE,
            'tasksSynchronizeOrder' => $tasksSynchronizeOrder
        ]);
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

    public function getTasksSynchronizeOrder(
        WC_Order  $order,
        ?stdClass $fDocentete,
        bool      $allChanges = true,
        bool      $getProductChanges = false,
        bool      $getShippingChanges = false,
        bool      $getFeeChanges = false,
        bool      $getCouponChanges = false,
        bool      $getTaxesChanges = false,
        bool      $getUserChanges = false,
    ): array
    {
        $result = [
            'allProductsExistInWordpress' => true,
            'syncChanges' => [],
            'products' => [],
        ];
        $taxeCodesProduct = [];
        $taxeCodesShipping = [];
        if (!$fDocentete) {
            return $result;
        }
        $getProductChanges = $allChanges || $getProductChanges;
        $getShippingChanges = $allChanges || $getShippingChanges;
        $getFeeChanges = $allChanges || $getFeeChanges;
        $getCouponChanges = $allChanges || $getCouponChanges;
        $getTaxesChanges = $allChanges || $getTaxesChanges;
        $getUserChanges = $allChanges || $getUserChanges;
        if ($getProductChanges || $getTaxesChanges) {
            [$productChanges, $products, $taxeCodesProduct] = $this->getTasksSynchronizeOrder_Products($order, $fDocentete->fDoclignes ?? []);
            $result['products'] = $products;
            if ($getProductChanges) {
                $result['syncChanges'] = [...$result['syncChanges'], ...$productChanges];
            }
        }
        if ($getShippingChanges || $getTaxesChanges) {
            [$shippingChanges, $taxeCodesShipping] = $this->getTasksSynchronizeOrder_Shipping($order, $fDocentete);
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
            $userChanges = $this->getTasksSynchronizeOrder_User($order, $fDocentete);
            $result['syncChanges'] = [...$result['syncChanges'], ...$userChanges];
        }

        $result['allProductsExistInWordpress'] = array_filter($fDocentete->fDoclignes, static function (stdClass $fDocligne) {
                return is_null($fDocligne->postId);
            }) === [];

        return $result;
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
            } else if (is_null($old)) {
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
        $new->name = current(array_filter($pExpeditions, static function (stdClass $pExpedition) use ($new) {
            return $pExpedition->slug === $new->method_id;
        }))->eIntitule;
        $new->priceHt = RoundUtils::round($fDocentete->fraisExpedition->priceHt);
        $new->priceTtc = RoundUtils::round($fDocentete->fraisExpedition->priceTtc);
        $new->taxes = [];
        if (!is_null($fDocentete->fraisExpedition->taxes)) {
            foreach ($fDocentete->fraisExpedition->taxes as $taxe) {
                $taxeCodes[] = $taxe->taCode;
                $new->taxes[$taxe->taxeNumber] = ['code' => $taxe->taCode, 'amount' => (float)$taxe->amount];
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

        // todo ajouter le bouton importer le document de vente dans Sage

        // todo calculer le prix de la livraison pour afficher le prix sur le site
        // todo return apply_filters( 'woocommerce_cart_shipping_method_full_label', $label, $method ); modifier le prix affiché au panier

        // todo pouvoir délier une commande wordpress d'une commande sage (garder l'historique des commandes auquels il a été lié)Do_Pi
        // todo dans le cas ou le document de vente n'existe plus et qu'il n'a pas été délié il faut pouvoir le délier

        // todo dans la page du compte d'un utilisateur ajouter bouton synchroniser avec sage ou affiché si c'est bien synchronisé avec Sage

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
        $userSage = $this->convertSageUserToWoocommerce($fComptet, userId: $userId);
        foreach (OrderUtils::ALL_ADDRESS_TYPE as $addressType) {
            $old = new stdClass();
            $new = new stdClass();
            $fields = [];
            foreach ($userMetaWordpress as $key => $value) {
                if (str_starts_with($key, $addressType)) {
                    $fields[] = $key;
                }
            }
            foreach ($userSage["meta"] as $key => $value) {
                if (str_starts_with($key, $addressType)) {
                    $fields[] = $key;
                }
            }
            $fields = array_values(array_unique($fields));
            foreach ($fields as $field) {
                if ($userMetaWordpress[$field][0] !== $userSage["meta"][$field]) {
                    $old->{$field} = $userMetaWordpress[$field][0];
                    $new->{$field} = $userSage["meta"][$field];
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
            return "<div class=error>" . __("L'adresse email n'est pas au bon format [email: '" . $email . "']", 'sage') . "</div>";
        }
        $mailExistsUserId = email_exists($email);
        if ($mailExistsUserId !== false && $mailExistsUserId !== $userId) {
            return "<div class=error>" . __('This email address [' . $email . '] is already registered for user id: ' . $mailExistsUserId . '.', 'woocommerce') . "</div>";
        }
        $fComptetAddress = Sage::createAddressWithFComptet($fComptet);
        $addressTypes = ['billing', 'shipping'];
        $address = [];
        $fPays = $this->sage->sageGraphQl->getFPays(false);
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
        foreach ($addressTypes as $addressType) {
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
        $result = [
            'name' => Sage::getName(intitule: $fComptet->ctIntitule, contact: $fComptet->ctContact), // Display name for the user
            'first_name' => $firstName, // First name for the user
            'last_name' => $lastName, // Last name for the user
            'email' => $email, // The email address for the user
            'meta' => $meta,
        ];
        if (is_null($userId)) {
            $result['username'] = $fComptet->ctNum;
            $result['password'] = bin2hex(random_bytes(5));
        }
        return $result;
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
                if (
                    ($oldValue = $order->{'get_' . $type . '_' . $key1}()) !==
                    $obj->{$key2}
                ) {
                    $old->{$key1} = $oldValue;
                    $new->{$key1} = $obj->{$key2};
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

    public function applyTasksSynchronizeOrder(WC_Order $order, array $tasksSynchronizeOrder): array
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
                    <p>" . __('Aucune action défini pour', 'sage') . ": " . print_r($syncChange['changes'], true) . "</p>
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

    private function updateOrderMetas(WC_Order $order, stdClass $new, string $addressType): string
    {
        $message = '';
        foreach ((array)$new as $key => $value) {
            $order->{'set_' . $addressType . '_' . $key}($value);
        }
        $order->save();
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

    private function addProductToOrder(WC_Order $order, ?int $productId, int $quantity, stdClass $new, array &$alreadyAddedTaxes): string
    {
        $message = '';
        $qty = wc_stock_amount($quantity);
        if (is_null($new->postId)) {
            [$response, $responseError, $message2] = $this->importFArticleFromSage($new->arRef, ignorePingApi: true);
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

    public function importFArticleFromSage(string $arRef, bool $ignorePingApi = false): array
    {
        $fArticle = $this->sage->sageGraphQl->getFArticle($arRef, ignorePingApi: $ignorePingApi);
        if (is_null($fArticle)) {
            return [null, null, "<div class='error'>
                        " . __("L'article n'a pas pu être importé", 'sage') . "
                                </div>"];
        }
        $articlePostId = $this->sage->sageWoocommerce->getWooCommerceIdArticle($arRef);
        $article = $this->sage->sageWoocommerce->convertSageArticleToWoocommerce($fArticle,
            current(array_filter($this->sage->settings->sageEntityMenus,
                static fn(SageEntityMenu $sageEntityMenu) => $sageEntityMenu->getMetaKeyIdentifier() === Sage::META_KEY_AR_REF
            ))
        );
        $url = '/wc/v3/products';
        if (!is_null($articlePostId)) {
            $url .= '/' . $articlePostId;
        }

        // cannot create an article without request
        // ========================================
        // created with: (new WC_REST_Products_Controller())->create_item($request);
        // woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php : public function create_item( $request )
        // which extends
        // woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-products-controller.php
        [$response, $responseError] = $this->sage->createResource(
            $url,
            is_null($articlePostId) ? 'POST' : 'PUT',
            $article,
            Sage::META_KEY_AR_REF,
            $arRef,
        );
        $dismissNotice = "<button type='button' class='notice-dismiss sage-notice-dismiss'><span class='screen-reader-text'>" . __('Dismiss this notice.') . "</span></button>";
        if (is_string($responseError)) {
            $message = $responseError;
        } else if ($response["response"]["code"] === 200) {
            $body = json_decode($response["body"], false, 512, JSON_THROW_ON_ERROR);
            $message = "<div class='notice notice-success is-dismissible'>
                    <p>" . __('Article mis à jour: ' . $body->name, 'sage') . "</p>
                    $dismissNotice
                            </div>";
        } else if ($response["response"]["code"] === 201) {
            $body = json_decode($response["body"], false, 512, JSON_THROW_ON_ERROR);
            $message = "<div class='notice notice-success is-dismissible'>
                    <p>" . __('Article créé: ' . $body->name, 'sage') . "</p>
                    $dismissNotice
                            </div>";
        } else {
            $message = $response["body"];
        }
        return [$response, $responseError, $message];
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
        foreach ($sageEntityMenu->getMetadata() as $metadata) {
            $value = $metadata->getValue();
            if (!is_null($value)) {
                $result['meta_data'][] = [
                    'key' => '_' . Sage::TOKEN . $metadata->getField(),
                    'value' => $value($fArticle),
                ];
            }
        }
        return $result;
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
        [$t, $rates] = $this->sage->settings->getWordpressTaxes();
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
                    <p>" . __('Il semblerait que la taxe ' . $taxe['code'] . ' soit manquante.', 'sage') . "</p>
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
        foreach ($new->taxes as $taxe) {
            $alreadyAddedTaxes[] = $taxe['code'];
        }
        $message = '';
        $wcShippingRate = new WC_Shipping_Rate();
        $wcShippingRate->set_label($new->name);
        $wcShippingRate->set_id($new->method_id);
        $wcShippingRate->set_cost($new->priceHt);
        $wcShippingRate->set_taxes($this->formatTaxes($order, $new->taxes, $message));
        $itemId = $order->add_shipping($wcShippingRate);

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
        [$t, $rates] = $this->sage->settings->getWordpressTaxes();
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
            foreach ($toRemove as $codeRemove) {
                foreach ($wcOrderItemTaxs as $wcOrderItemTax) {
                    if ($wcOrderItemTax->get_label() === $codeRemove) {
                        $wcOrderItemTax->delete();
                        // no break because can have multiple same label
                    }
                }
            }
        }
        return $message;
    }

    private function removeProductOrder(WC_Order $order, int $itemId): string
    {
        $message = '';
        $lineItems = array_values($order->get_items());

        foreach ($lineItems as $lineItem) {
            if ($lineItem->get_id() === $itemId) {
                $lineItem->delete();
                break;
            }
        }
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
            [$userId, $message] = $this->sage->importUserFromSage($new->ctNum, ignorePingApi: true);
            if (!is_numeric($userId)) {
                return $message;
            }
        }
        $order->set_customer_id($userId);
        $order->save();

        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        $fDocentete = $this->sage->sageGraphQl->getFDocentete(
            $fDocenteteIdentifier["doPiece"],
            $fDocenteteIdentifier["doType"],
            getError: true,
            getFDoclignes: true,
            getExpedition: true,
            ignorePingApi: true,
            addWordpressProductId: true,
            getUser: true,
            getLivraison: true,
        );

        if (!is_string($fDocentete)) {
            $this->applyTasksSynchronizeOrder($order, $this->getTasksSynchronizeOrder(
                $order,
                $fDocentete,
                allChanges: false,
                getUserChanges: true,
            ));
        } else {
            $message .= $fDocentete;
        }

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

    public function calculateFraisExpedition(WC_Order $order): float
    {
        // todo implements
        $pExpeditions = $this->sage->sageGraphQl->getPExpeditions(
            getError: true,
        );
        return 0;
    }
}
