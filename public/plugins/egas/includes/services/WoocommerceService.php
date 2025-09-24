<?php

namespace App\services;

use App\controllers\AdminController;
use App\enum\Sage\ArticleTypeEnum;
use App\enum\Sage\DocumentFraisTypeEnum;
use App\enum\Sage\ETypeCalculEnum;
use App\enum\Sage\NomenclatureTypeEnum;
use App\enum\Sage\TaxeTauxType;
use App\resources\FArticleResource;
use App\resources\FComptetResource;
use App\resources\FDocenteteResource;
use App\resources\Resource;
use App\Sage;
use App\Utils\FDocenteteUtils;
use App\Utils\OrderUtils;
use App\Utils\TaxeUtils;
use stdClass;
use WC_Cart;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Item_Shipping;
use WC_Order_Item_Tax;
use WC_Product;
use WC_Product_Simple;
use WC_Shipping_Rate;
use WC_Tax;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

class WoocommerceService
{
    private static ?WoocommerceService $instance = null;

    private function __construct()
    {
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
            $ctNum = WordpressService::getInstance()->getUserWordpressIdForSage($mailExistsUserId);
            if (!empty($ctNum)) {
                return [null, "<div class='notice notice-error is-dismissible'>" . __('This email address [' . $email . '] is already registered for user id: ' . $mailExistsUserId . '.', 'woocommerce') . "</div>", null];
            }
            $userId = $mailExistsUserId;
            SageService::getInstance()->updateUserOrFComptet($ctNum, $userId, $fComptet);
        }
        $sageService = SageService::getInstance();
        $fComptetAddress = $sageService->createAddressWithFComptet($fComptet);
        $address = [];
        $fPays = GraphqlService::getInstance()->getFPays(false, ignorePingApi: true);
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
        $resource = FComptetResource::getInstance();
        foreach ($resource->getMetadata() as $metadata) {
            $value = $metadata->getValue();
            if (!is_null($value)) {
                $meta['_' . Sage::TOKEN . $metadata->getField()] = $value($fComptet);
            }
        }
        foreach (OrderUtils::ALL_ADDRESS_TYPE as $addressType) {
            $thisAddress = $address[$addressType];
            [$firstName, $lastName] = $sageService->getFirstNameLastName(
                $thisAddress->liIntitule,
                $thisAddress->liContact
            );
            $fPay = current(array_filter($fPays, static fn(StdClass $fPay) => $fPay->paIntitule === $thisAddress->liPays));
            $meta = [
                ...$meta,
                // region woocommerce (got from: woocommerce/includes/class-wc-privacy-erasers.php)
                $addressType . '_first_name' => $firstName,
                $addressType . '_last_name' => $lastName,
                $addressType . '_company' => $sageService->getName(intitule: $thisAddress->liIntitule, contact: $thisAddress->liContact),
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
        [$firstName, $lastName] = $sageService->getFirstNameLastName(
            $fComptet->ctIntitule,
            $fComptet->ctContact
        );
        $wpUser = new WP_User($userId ?? 0);
        $wpUser->display_name = $sageService->getName(intitule: $fComptet->ctIntitule, contact: $fComptet->ctContact);
        $wpUser->first_name = $firstName;
        $wpUser->last_name = $lastName;
        $wpUser->user_email = $email;

        if (is_null($userId)) {
            $wpUser->user_login = $sageService->getAvailableUserName($fComptet->ctNum);
            $wpUser->user_pass = bin2hex(random_bytes(5));
        }

        return [$userId, $wpUser, $meta];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function afterCreateOrEditOrder(WC_Order $order, bool $isNewOrder = false): void
    {
        if (
            array_key_exists(Sage::TOKEN . '-fdocentete-dotype', $_POST) &&
            array_key_exists(Sage::TOKEN . '-fdocentete-dopiece', $_POST) &&
            is_numeric($_POST[Sage::TOKEN . '-fdocentete-dotype']) &&
            !empty($_POST[Sage::TOKEN . '-fdocentete-dopiece'])
        ) {
            $this->linkOrderFDocentete(
                $order,
                $_POST[Sage::TOKEN . '-fdocentete-dopiece'],
                (int)$_POST[Sage::TOKEN . '-fdocentete-dotype'],
                true,
            );
        } else if ($isNewOrder) {
            $optionName = Sage::TOKEN . '_auto_create_' . Sage::TOKEN . '_fdocentete';
            if (get_option($optionName)) {
                $order->update_meta_data($optionName, true);
                $order->save();
            }
        }
    }

    public function linkOrderFDocentete(WC_Order $order, string $doPiece, int $doType, bool $ignorePingApi, array $headers = []): WC_Order
    {
        $graphqlService = GraphqlService::getInstance();
        $fDocentete = $graphqlService->getFDocentetes(
            $doPiece,
            [$doType],
            doDomaine: FDocenteteUtils::DO_DOMAINE_VENTE,
            doProvenance: FDocenteteUtils::DO_PROVENANCE_NORMAL,
            ignorePingApi: $ignorePingApi,
            single: true
        );
        if ($fDocentete instanceof stdClass) {
            $order->update_meta_data(FComptetResource::META_KEY, json_encode([
                'doPiece' => $fDocentete->doPiece,
                'doType' => $fDocentete->doType,
            ], JSON_THROW_ON_ERROR));
            $order->save();
            $extendedFDocentetes = $graphqlService->getFDocentetes(
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
        $sageService = SageService::getInstance();
        $fDoclignes = $sageService->getFDoclignes($extendedFDocentetes);
        $mainFDocentete = $sageService->getMainFDocenteteOfExtendedFDocentetes($order, $extendedFDocentetes);
        if ($getProductChanges || $getTaxesChanges) {
            [$productChanges, $products, $taxeCodesProduct] = $sageService->getTasksSynchronizeOrder_Products($order, $fDoclignes);
            $result['products'] = $products;
            if ($getProductChanges) {
                $result['syncChanges'] = [...$result['syncChanges'], ...$productChanges];
            }
        }
        if ($getShippingChanges || $getTaxesChanges) {
            [$shippingChanges, $taxeCodesShipping] = $sageService->getTasksSynchronizeOrder_Shipping($order, $mainFDocentete);
            if ($getShippingChanges) {
                $result['syncChanges'] = [...$result['syncChanges'], ...$shippingChanges];
            }
        }
        if ($getFeeChanges) {
            $feeChanges = $sageService->getTasksSynchronizeOrder_Fee($order);
            $result['syncChanges'] = [...$result['syncChanges'], ...$feeChanges];
        }
        if ($getCouponChanges) {
            $couponChanges = $sageService->getTasksSynchronizeOrder_Coupon($order);
            $result['syncChanges'] = [...$result['syncChanges'], ...$couponChanges];
        }
        if ($getTaxesChanges) {
            $taxeCodesProduct = array_values(array_unique([...$taxeCodesProduct, ...$taxeCodesShipping]));
            $taxesChanges = $sageService->getTasksSynchronizeOrder_Taxes($order, $taxeCodesProduct);
            $result['syncChanges'] = [...$result['syncChanges'], ...$taxesChanges];
        }
        if ($getUserChanges) {
            $userChanges = $sageService->getTasksSynchronizeOrder_User($order, $mainFDocentete);
            $result['syncChanges'] = [...$result['syncChanges'], ...$userChanges];
        }

        $result['allProductsExistInWordpress'] = array_filter($fDoclignes, static function (stdClass $fDocligne) {
                return is_null($fDocligne->postId);
            }) === [];

        return $result;
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
            $fArticle = GraphqlService::getInstance()->getFArticle($arRef, ignorePingApi: $ignorePingApi);
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
        $articlePostId = $this->getWooCommerceIdArticle($arRef);
        $isCreation = is_null($articlePostId);
        $article = $this->convertSageArticleToWoocommerce($fArticle, SageService::getInstance()->getResource(FArticleResource::ENTITY_NAME));
        $dismissNotice = "<button type='button' class='notice-dismiss " . Sage::TOKEN . "-notice-dismiss'><span class='screen-reader-text'>" . __('Dismiss this notice.') . "</span></button>";
        $urlArticle = "<strong><span style='display: block; clear: both;'><a href='" . get_admin_url() . "post.php?post=%id%&action=edit'>" . __("Voir l'article", Sage::TOKEN) . "</a></span></strong>";
        if ($isCreation) {
            // cannot create an article without request
            // ========================================
            // created with: (new WC_REST_Products_Controller())->create_item($request);
            // woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-crud-controller.php : public function create_item( $request )
            // which extends
            // woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-products-controller.php
            [$response, $responseError] = SageService::getInstance()->createResource(
                '/wc/v3/products/' . $articlePostId,
                'POST',
                $article,
                FArticleResource::META_KEY,
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
", [FArticleResource::META_KEY, $arRef]));
        if (!empty($r)) {
            return (int)$r[0]->ID;
        }
        return null;
    }

    public function convertSageArticleToWoocommerce(StdClass $fArticle, Resource $resource): array
    {
        $result = [
            'name' => $fArticle->arDesign,
            'meta_data' => [],
        ];
        foreach ($resource->getMetadata($fArticle) as $metadata) {
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
        [$taxe, $rates] = $this->getWordpressTaxes();
        $result = ['total' => [], 'subtotal' => []];
        foreach ($taxes as $taxe) {
            $rate = current(array_filter($rates, static function (stdClass $rate) use ($taxe) {
                return $rate->tax_rate_name === $taxe['code'];
            }));
            if ($rate === false) {
                if ($errorMissingTax === 0) {
                    $errorMissingTax++;
                    $this->updateTaxes();
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

    public function getWordpressTaxes(): array
    {
        $taxes = WC_Tax::get_tax_rate_classes();
        $taxe = current(array_filter($taxes, static function (stdClass $taxe) {
            return $taxe->slug === Sage::TOKEN;
        }));
        if ($taxe === false) {
            WC_Tax::create_tax_class(__('Sage', Sage::TOKEN), Sage::TOKEN);
            $taxes = WC_Tax::get_tax_rate_classes();
            $taxe = current(array_filter($taxes, static function (stdClass $taxe) {
                return $taxe->slug === Sage::TOKEN;
            }));
        }
        $rates = WC_Tax::get_rates_for_tax_class($taxe->slug);
        return [$taxe, $rates];
    }

    public function updateTaxes(bool $showMessage = true): void
    {
        [$taxe, $rates] = $this->getWordpressTaxes();
        $fTaxes = GraphqlService::getInstance()->getFTaxes(useCache: false, getFromSage: true);
        if (!AdminController::showErrors($fTaxes)) {
            $taxeChanges = $this->getTaxesChanges($fTaxes, $rates);
            $this->applyTaxesChanges($taxeChanges);
            if ($showMessage && $taxeChanges !== []) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?= __("Les taxes Sage ont été mises à jour.", Sage::TOKEN) ?></strong></p>
                </div>
                <?php
            }
        }
    }

    private function getTaxesChanges(array $fTaxes, array $rates): array
    {
        $taxeChanges = [];
        $compareFunction = function (stdClass $fTaxe, stdClass $rate) {
            $taTaux = (float)($fTaxe->taNp === 1 ? 0 : $fTaxe->taTaux);
            return
                $fTaxe->taCode === $rate->tax_rate_name &&
                $taTaux === (float)$rate->tax_rate &&
                $rate->tax_rate_country === '' &&
                $rate->postcode_count === 0 &&
                $rate->city_count === 0;
        };
        foreach ($fTaxes as $fTaxe) {
            $rate = current(array_filter($rates, static function (stdClass $rate) use ($compareFunction, $fTaxe) {
                return $compareFunction($fTaxe, $rate);
            }));
            if ($rate === false) {
                $taxeChanges[] = [
                    'old' => null,
                    'new' => $fTaxe,
                    'change' => TaxeUtils::ADD_TAXE_ACTION,
                ];
            }
        }
        foreach ($rates as $rate) {
            $fTaxe = current(array_filter($fTaxes, static function (stdClass $fTaxe) use ($compareFunction, $rate) {
                return $compareFunction($fTaxe, $rate);
            }));
            if ($fTaxe === false) {
                $taxeChanges[] = [
                    'old' => $rate,
                    'new' => null,
                    'change' => TaxeUtils::REMOVE_TAXE_ACTION,
                ];
            }
        }
        return $taxeChanges;
    }

    public function applyTaxesChanges(array $taxeChanges): void
    {
        foreach ($taxeChanges as $taxeChange) {
            if ($taxeChange["change"] === TaxeUtils::ADD_TAXE_ACTION) {
                WC_Tax::_insert_tax_rate([
                    "tax_rate_country" => "",
                    "tax_rate_state" => "",
                    "tax_rate" => $taxeChange["new"]->taNp === 1 ? 0 : (string)$taxeChange["new"]->taTaux,
                    "tax_rate_name" => $taxeChange["new"]->taCode,
                    "tax_rate_priority" => "1",
                    "tax_rate_compound" => "0",
                    "tax_rate_shipping" => "1",
                    "tax_rate_class" => Sage::TOKEN
                ]);
            } else if ($taxeChange["change"] === TaxeUtils::REMOVE_TAXE_ACTION) {
                WC_Tax::_delete_tax_rate($taxeChange["old"]->tax_rate_id);
            }
        }
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
        [$taxe, $rates] = $this->getWordpressTaxes();
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

    public function getToRemoveToAddTaxes(WC_Order $order, array $new): array
    {
        $current = array_values(array_map(static function (WC_Order_Item_Tax $wcOrderItemTax) {
            return $wcOrderItemTax->get_label();
        }, $order->get_taxes()));
        $toRemove = array_diff($current, $new);
        $toAdd = array_diff($new, $current);
        return [$toRemove, $toAdd];
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
            [$userId, $message] = SageService::getInstance()->updateUserOrFComptet($new->ctNum, ignorePingApi: true);
            if (!is_numeric($userId)) {
                return $message;
            }
        }
        $order->set_customer_id($userId);
        $order->save();

        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
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

    public function getFDocenteteIdentifierFromOrder(WC_Order $order): array|null
    {
        $fDocenteteIdentifier = null;
        foreach ($order->get_meta_data() as $meta) {
            $data = $meta->get_data();
            if ($data['key'] === FDocenteteResource::META_KEY) {
                $fDocenteteIdentifier = json_decode($data['value'], true, 512, JSON_THROW_ON_ERROR);
                break;
            }
        }
        return $fDocenteteIdentifier;
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
                $value = WordpressService::getInstance()->getValidWordpressMail($value);
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

    public function getShippingRateCosts(WC_Cart $wcCart, WC_Shipping_Rate $wcShippingRate): float|null
    {
        $pExpeditions = GraphqlService::getInstance()->getPExpeditions();
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
                    /** @var  WC_Meta_Data[] $metaDatas */
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

    public function desynchronizeOrder(WC_Order $order): WC_Order
    {
        $fDocenteteIdentifier = $this->getFDocenteteIdentifierFromOrder($order);
        if (!empty($fDocenteteIdentifier)) {
            $order->add_order_note(__('Le document de vente Sage a été désynchronisé de la commande.', Sage::TOKEN) . ' [' . $fDocenteteIdentifier["doPiece"] . ']');
            $order->delete_meta_data(FComptetResource::META_KEY);
            $order->save();
        }
        return $order;
    }

    public function importFDocenteteFromSage(string $doPiece, string $doType, array $headers = []): array
    {
        $order = new WC_Order();
        $order = $this->linkOrderFDocentete($order, $doPiece, $doType, true, headers: $headers);
        $extendedFDocentetes = GraphqlService::getInstance()->getFDocentetes(
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
}
