<?php

namespace App\lib;

use App\class\SageEntityMenu;
use App\Sage;
use App\SageSettings;
use StdClass;
use WC_Meta_Data;
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
                    $priceData = current(array_filter($pricesData, static fn(array $p) => $p['CbMarq'] === $nCatTarifCbMarq->cbMarq));
                    if ($priceData !== false) {
                        $price = $priceData["PriceTtc"];
                    }
                }
            }
            if (empty($price)) {
                $allPrices = array_map(static function (array $priceData) {
                    return $priceData['PriceTtc'];
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
        $fPays = $this->sage->sageGraphQl->getFPays();
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

    public function getWooCommerceIdArticle(string $arRef): int|null
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare(
                "
SELECT {$wpdb->posts}.ID
FROM {$wpdb->posts}
         INNER JOIN wp_postmeta ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
WHERE {$wpdb->posts}.post_type = 'product'
  AND {$wpdb->postmeta}.meta_key = %s
  AND {$wpdb->postmeta}.meta_value = %s
", [Sage::META_KEY_AR_REF, $arRef]));
        if (!empty($r)) {
            return (int)$r[0]->ID;
        }
        return null;
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
        $table = 'wp_postmeta';
        $idColumn = 'post_id';
        if ($metaKeyIdentifier === Sage::META_KEY_CT_NUM) {
            $table = 'wp_usermeta';
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

        $mapping = array_flip($mapping);
        foreach ($data["data"][$entityName]["items"] as &$item) {
            foreach ($fieldNames as $fieldName) {
                if (isset($results[$item[$mandatoryField]][$fieldName])) {
                    $item[$fieldName] = $results[$item[$mandatoryField]][$fieldName];
                } else {
                    $item[$fieldName] = '';
                }
            }
            $item['_' . Sage::TOKEN . '_postId'] = null;
            if (array_key_exists($item[$mandatoryField], $mapping)) {
                $item['_' . Sage::TOKEN . '_postId'] = $mapping[$item[$mandatoryField]];
            }
        }
        return $data;
    }
}
