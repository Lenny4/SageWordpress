<?php

namespace App\lib;

use App\class\SageEntityMenu;
use App\Sage;
use App\SageSettings;
use App\Utils\FDocenteteUtils;
use App\Utils\OrderUtils;
use Automattic\WooCommerce\Admin\Overrides\Order;
use StdClass;
use WC_Meta_Data;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;

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
        $pCattarifs = Sage::getPCattarifs();
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

    public function convertSageUserToWoocommerce(StdClass $fComptet, ?int $userId, SageEntityMenu $sageEntityMenu): array|string
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
        foreach ($addressTypes as $addressType) {
            $thisAdress = current(array_filter($fComptet->fLivraisons, static function (StdClass $fLivraison) use ($addressType, $fComptetAddress) {
                if ($addressType === 'billing') {
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
        foreach ($sageEntityMenu->getMetadata() as $metadata) {
            $value = $metadata->getValue();
            if (!is_null($value)) {
                $meta['_' . Sage::TOKEN . $metadata->getField()] = $value($fComptet);
            }
        }
        foreach ($addressTypes as $addressType) {
            $thisAddress = $address[$addressType];
            [$firstName, $lastName] = Sage::getFirstNameLastName(
                intitule: $thisAddress->liIntitule,
                contact: $thisAddress->liContact
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
                $addressType . '_country' => $fPay !== false ? $fPay->paCode : $thisAddress->liPays,
                $addressType . '_phone' => $thisAddress->liTelephone,
                $addressType . '_email' => $thisAddress->liEmail,
                // endregion
            ];
        }
        [$firstName, $lastName] = Sage::getFirstNameLastName(
            intitule: $fComptet->ctIntitule,
            contact: $fComptet->ctContact
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
        $ids = array_map(static function (array $entity) use ($mandatoryField) {
            return $entity[$mandatoryField];
        }, $data["data"][$entityName]["items"]);

        $metaKeyIdentifier = $sageEntityMenu->getMetaKeyIdentifier();
        global $wpdb;
        $table = $wpdb->postmeta;
        $idColumn = 'post_id';
        if ($metaKeyIdentifier === Sage::META_KEY_CT_NUM) {
            $table = $wpdb->usermeta;
            $idColumn = 'user_id';
        }
        $temps = $wpdb->get_results("
SELECT " . $table . "2." . $idColumn . " post_id, " . $table . "2.meta_value, " . $table . "2.meta_key
FROM " . $table . "
         LEFT JOIN " . $table . " " . $table . "2 ON " . $table . "2." . $idColumn . " = " . $table . "." . $idColumn . "
WHERE " . $table . ".meta_value IN ('" . implode("','", $ids) . "')
  AND " . $table . "2.meta_key IN ('" . implode("','", [$metaKeyIdentifier, ...$fieldNames]) . "')
ORDER BY " . $table . "2.meta_key = '" . $metaKeyIdentifier . "' DESC;
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
                if (array_key_exists($item[$mandatoryField], $mapping)) {
                    $item['_' . Sage::TOKEN . '_postId'] = $mapping[$item[$mandatoryField]];
                }
            }
        }
        return $data;
    }

    public function getMetaboxSage(Order $order, bool $ignorePingApi = false, string $message = ''): string
    {
        $fDocenteteIdentifier = null;
        foreach ($order->get_meta_data() as $meta) {
            $data = $meta->get_data();
            if ($data['key'] === '_' . Sage::TOKEN . '_identifier') {
                $fDocenteteIdentifier = json_decode($data['value'], true, 512, JSON_THROW_ON_ERROR);
                break;
            }
        }
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
            );
            if (is_string($fDocentete)) {
                $message .= $fDocentete;
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

    public function getTasksSynchronizeOrder(Order $order, ?stdClass $fDocentete): array
    {
        [$productChanges, $products] = $this->getTasksSynchronizeOrder_Products($order, $fDocentete?->fDoclignes ?? []);
        $shippingChanges = $this->getTasksSynchronizeOrder_Shipping($order, $fDocentete);

        // region addresses
        // endregion

        // region contact
        // endregion

        return [
            'allProductsExistInWordpress' => $fDocentete && array_filter($fDocentete->fDoclignes, static function (stdClass $fDocligne) {
                    return is_null($fDocligne->postId);
                }) === [],
            'productChanges' => $productChanges,
            'products' => $products,
        ];
    }

    private function getTasksSynchronizeOrder_Products(Order $order, array $fDoclignes): array
    {
        // to get order data: wp-content/plugins/woocommerce/includes/admin/meta-boxes/views/html-order-items.php:24
        $lineItems = array_values($order->get_items());

        // region products
        $nbLines = max(count($lineItems), count($fDoclignes));
        $productChanges = [];
        for ($i = 0; $i < $nbLines; $i++) {
            $old = null;
            if (isset($lineItems[$i])) {
                $data = $lineItems[$i]->get_data();
                $old = new stdClass();
                $old->postId = $data["product_id"];
                $old->quantity = $data["quantity"];
            }
            $new = null;
            if (isset($fDoclignes[$i])) {
                $new = new stdClass();
                $new->postId = $fDoclignes[$i]->postId;
                $new->quantity = (int)$fDoclignes[$i]->dlQte;
            }
            $change = null;
            if (!is_null($new) && !is_null($old)) {
                if ($new->postId !== $old->postId) {
                    $change = OrderUtils::REPLACE_PRODUCT_ACTION;
                } else if ($new->quantity !== $old->quantity) {
                    $change = OrderUtils::CHANGE_QUANTITY_PRODUCT_ACTION;
                }
            } else if (is_null($new)) {
                $change = OrderUtils::REMOVE_PRODUCT_ACTION;
            } else if (is_null($old)) {
                $change = OrderUtils::ADD_PRODUCT_ACTION;
            }
            if (is_null($old) && is_null($new->postId)) {
                continue;
            }
            $productChanges[$i] = [
                'old' => $old,
                'new' => $new,
                'change' => $change,
            ];
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
        return [$productChanges, $products];
    }

    private function getTasksSynchronizeOrder_Shipping(Order $order, ?stdClass $fDocentete): array
    {
        // to get order data: wp-content/plugins/woocommerce/includes/admin/meta-boxes/views/html-order-items.php:27
        $lineItemsShipping = $order->get_items('shipping');

        // faire la différence entre pas de shipping (retrait en magasin) et shipping gratuit, dans les 2 cas ça vaut 0
        //mais y'en a 1 ou il faut afficher le shipping et l'autre non

        // todo return apply_filters( 'woocommerce_cart_shipping_method_full_label', $label, $method ); modifier le prix affiché au panier
        // todo faire en JS: https://stackoverflow.com/a/6036392/6824121

        $shippingChanges = [];

        return $shippingChanges;
    }

    public function importFArticleFromSage(string $arRef): array
    {
        $fArticle = $this->sage->sageGraphQl->getFArticle($arRef);
        if (is_null($fArticle)) {
            return [null, "<div class='error'>
                        " . __("L'article n'a pas pu être importé", 'sage') . "
                                </div>"];
        }
        $articlePostId = $this->sage->sageWoocommerce->getWooCommerceIdArticle($arRef);
        $article = $this->sage->sageWoocommerce->convertSageArticleToWoocommerce($fArticle,
            current(array_filter($this->sage->settings->sageEntityMenus,
                static fn(SageEntityMenu $sageEntityMenu) => $sageEntityMenu->getMetaKeyIdentifier() === Sage::META_KEY_AR_REF
            ))
        );
        $url = '/wp-json/wc/v3/products';
        if (!is_null($articlePostId)) {
            $url .= '/' . $articlePostId;
        }

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
                    <p>" . __('Article updated: ' . $body->name, 'sage') . "</p>
                    $dismissNotice
                            </div>";
        } else if ($response["response"]["code"] === 201) {
            $body = json_decode($response["body"], false, 512, JSON_THROW_ON_ERROR);
            $message = "<div class='notice notice-success is-dismissible'>
                    <p>" . __('Article created: ' . $body->name, 'sage') . "</p>
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
}
