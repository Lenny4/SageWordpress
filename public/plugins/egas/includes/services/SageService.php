<?php

namespace App\services;

use App\class\dto\ArgumentSelectionSetDto;
use App\class\SageEntityMetadata;
use App\controllers\AdminController;
use App\resources\FArticleResource;
use App\resources\FComptetResource;
use App\resources\Resource;
use App\Sage;
use App\utils\FDocenteteUtils;
use App\utils\OrderUtils;
use App\utils\PathUtils;
use App\utils\RoundUtils;
use App\utils\SageTranslationUtils;
use StdClass;
use Symfony\Component\HttpFoundation\Response;
use WC_Order;
use WC_Order_Item_Tax;
use WC_Product;
use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

class SageService
{
    private static ?SageService $instance = null;
    public ?array $resources = null;

    public function createAddressWithFComptet(StdClass $fComptet): StdClass
    {
        $r = new StdClass();
        $r->liIntitule = $fComptet->ctIntitule;
        $r->liAdresse = $fComptet->ctAdresse;
        $r->liComplement = $fComptet->ctComplement;
        $r->liCodePostal = $fComptet->ctCodePostal;
        $r->liPrincipal = 0;
        $r->liVille = $fComptet->ctVille;
        $r->liPays = $fComptet->ctPays;
        $r->liPaysCode = $fComptet->ctPaysCode;
        $r->liContact = $fComptet->ctContact;
        $r->liTelephone = $fComptet->ctTelephone;
        $r->liEmail = $fComptet->ctEmail;
        $r->liCodeRegion = $fComptet->ctCodeRegion;
        $r->liAdresseFact = 0;
        return $r;
    }

    public function getName(?string $intitule, ?string $contact): string
    {
        $intitule = trim($intitule ?? '');
        $contact = trim($contact ?? '');
        $name = $intitule;
        if (empty($name)) {
            $name = $contact;
        }
        return $name;
    }

    public function getAvailableUserName(string $ctNum): string
    {
        global $wpdb;
        $r = $wpdb->get_results(
            $wpdb->prepare("
SELECT user_login
FROM {$wpdb->users}
WHERE user_login LIKE %s
", [$ctNum . '%']));
        if (!empty($r)) {
            $names = array_map(static function (stdClass $user) {
                return $user->user_login;
            }, $r);
            $result = null;
            $i = 1;
            while (is_null($result)) {
                $newName = $ctNum . $i;
                if (!in_array($newName, $names, true)) {
                    $result = $newName;
                }
                $i++;
            }
            return $result;
        }
        return $ctNum;
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
        $fDocenteteIdentifier = WoocommerceService::getInstance()->getFDocenteteIdentifierFromOrder($order);
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

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getTasksSynchronizeOrder_Products(WC_Order $order, array $fDoclignes): array
    {
        $taxeCodes = [];
        // to get order data: wp-content/plugins/woocommerce/includes/admin/meta-boxes/views/html-order-items.php:24
        $lineItems = array_values($order->get_items());

        $nbLines = max(count($lineItems), count($fDoclignes));
        $productChanges = [];
        [$taxe, $rates] = WoocommerceService::getInstance()->getWordpressTaxes();
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

    public function getTasksSynchronizeOrder_Shipping(WC_Order $order, stdClass $fDocentete): array
    {
        $taxeCodes = [];
        [$taxe, $rates] = WoocommerceService::getInstance()->getWordpressTaxes();
        $pExpeditions = GraphqlService::getInstance()->getPExpeditions(
            getError: true, // on admin page
        );
        if (AdminController::showErrors($pExpeditions)) {
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
        return [$shippingChanges, $taxeCodes];
    }

    public function getTasksSynchronizeOrder_Fee(WC_Order $order): array
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

    public function getTasksSynchronizeOrder_Coupon(WC_Order $order): array
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

    public function getTasksSynchronizeOrder_Taxes(WC_Order $order, array $new): array
    {
        $taxesChanges = [];
        $old = array_values(array_map(static function (WC_Order_Item_Tax $wcOrderItemTax) {
            return $wcOrderItemTax->get_label();
        }, $order->get_taxes()));
        $changes = [];
        [$toRemove, $toAdd] = WoocommerceService::getInstance()->getToRemoveToAddTaxes($order, $new);
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

    public function getTasksSynchronizeOrder_User(WC_Order $order, stdClass $fDocentete): array
    {
        $userChanges = [];
        $orderUserId = $order->get_user_id();
        $ctNum = $fDocentete->doTiers;
        $expectedUserId = WordpressService::getInstance()->getUserIdWithCtNum($ctNum);
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
                $fDocentete->liNoNavigation
            )];
        }
        return $userChanges;
    }

    private function getUserChanges(int $userId, stdClass $fComptet): array
    {
        $userChanges = [];
        $userMetaWordpress = get_user_meta($userId);
        [$userId, $userFromSage, $metadata] = WoocommerceService::getInstance()->convertFComptetToUser($fComptet, userId: $userId);
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
            [$firstName, $lastName] = $this->getFirstNameLastName($obj->{$prefix . 'Contact'}, $obj->{$prefix . 'Intitule'});
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
                    $objValue = WordpressService::getInstance()->getValidWordpressMail($objValue);
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

    public function getFirstNameLastName(...$fullNames): array
    {
        foreach ($fullNames as $fullName) {
            if (empty($fullName)) {
                continue;
            }
            $fullName = trim($fullName);
            $lastName = (!str_contains($fullName, ' ')) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $fullName);
            $firstName = trim(preg_replace('#' . preg_quote($lastName, '#') . '#', '', $fullName));
            return [$firstName, $lastName];
        }
        return ['', ''];
    }

    public function getAllFilterType(): array
    {
        $result = [];
        $allFilterType = GraphqlService::getInstance()->getAllFilterType() ?? [];
        foreach ($allFilterType as $filterType) {
            if (
                $filterType->kind !== 'INPUT_OBJECT' ||
                !str_contains($filterType->name, 'Operation')
            ) {
                continue;
            }
            $result[$filterType->name] = array_values(array_filter(array_map(function (stdClass $value) {
                return $value->name;
            }, $filterType->inputFields), function (string $item) {
                return !in_array($item, ["and", "or"]);
            }));
        }
        return $result;
    }

    public function createResource(
        string  $url,
        string  $method,
        array   $body,
        ?string $deleteKey,
        ?string $deleteValue,
        array   $headers = [],
    ): array
    {
        if (!is_null($deleteKey) && !is_null($deleteValue)) {
            WordpressService::getInstance()->deleteMetaTrashResource($deleteKey, $deleteValue);
        }
        $response = RequestService::getInstance()->selfRequest($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                ...$headers,
            ],
            'method' => $method,
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $responseError = null;
        if ($response instanceof WP_Error) {
            $responseError = "<div class='notice notice-error is-dismissible'>
                                <pre>" . $response->get_error_code() . "</pre>
                                <pre>" . $response->get_error_message() . "</pre>
                                </div>";
        }

        if ($response instanceof WP_Error) {
            $responseError = "<div class='notice notice-error is-dismissible'>
                                <pre>" . json_encode($response, JSON_THROW_ON_ERROR) . "</pre>
                                </div>";
        } else if (!in_array($response["response"]["code"], [Response::HTTP_OK, Response::HTTP_CREATED], true)) {
            $responseError = "<div class='notice notice-error is-dismissible'>
                                <pre>" . $response['response']['code'] . "</pre>
                                <pre>" . $response['body'] . "</pre>
                                </div>";
        }
        return [$response, $responseError];
    }

    /**
     * If fComptet is more up to date than user -> update user in wordpress
     * If user is more up to date than fComptet -> update fComptet in sage
     */
    public function importFComptetFromSage(
        ?string              $ctNum,
        stdClass|string|null $fComptet = null,
    ): array
    {
        if (is_null($ctNum)) {
            return [null, "<div class='error'>
                    " . __("Vous devez spécifier le numéro de compte Sage", Sage::TOKEN) . "
                            </div>"];
        }
        $fComptet ??= GraphqlService::getInstance()->getFComptet($ctNum);
        if (is_null($fComptet)) {
            return [null, "<div class='error'>
                    " . __("Le compte Sage n'a pas pu être importé", Sage::TOKEN) . "
                            </div>"];
        }
        $resource = SageService::getInstance()->getResource(FComptetResource::ENTITY_NAME);
        $canImportFComptet = $resource->getCanImport()($fComptet);
        if (!empty($canImportFComptet)) {
            return [null, "<div class='error'>
                        " . implode(' ', $canImportFComptet) . "
                                </div>"];
        }
        $ctNum = $fComptet->ctNum;
        $userId = WordpressService::getInstance()->getUserIdWithCtNum($ctNum);
        [$userId, $wpUser, $metadata] = WoocommerceService::getInstance()->convertFComptetToUser(
            $fComptet,
            $userId,
        );
        if (is_string($wpUser)) {
            return [null, $wpUser];
        }
        $newUser = is_null($userId);
        if ($newUser) {
            $userId = wp_create_user($wpUser->user_login, $wpUser->user_pass, $wpUser->user_email);
        }
        if ($userId instanceof WP_Error) {
            return [null, "<div class='notice notice-error is-dismissible'>
                                <pre>" . $userId->get_error_code() . "</pre>
                                <pre>" . $userId->get_error_message() . "</pre>
                                </div>"];
        }
        $wpUser = new WP_User($userId);
        wp_update_user($wpUser);
        foreach ($metadata as $key => $value) {
            update_user_meta($userId, $key, $value);
        }
        $url = "<strong><span style='display: block; clear: both;'><a href='" . get_admin_url() . "user-edit.php?user_id=" . $userId . "'>" . __("Voir l'utilisateur", Sage::TOKEN) . "</a></span></strong>";
        if (!$newUser) {
            return [$userId, "<div class='notice notice-success is-dismissible'>
                        " . __('L\'utilisateur a été modifié', Sage::TOKEN) . $url . "
                                </div>"];
        }
        return [$userId, "<div class='notice notice-success is-dismissible'>
                        " . __('L\'utilisateur a été créé', Sage::TOKEN) . $url . "
                                </div>"];
    }

    public function getResource(string $entityName): Resource|null
    {
        $resources = $this->getResources();
        foreach ($resources as $resource) {
            if ($resource->getEntityName() == $entityName) {
                return $resource;
            }
        }
        return null;
    }

    /**
     * @return Resource[]
     */
    public function getResources(): array
    {
        if (is_null($this->resources)) {
            /** @var Resource[] $resources */
            $resources = [];
            $files = glob(__DIR__ . '/../resources' . '/*.php');
            foreach ($files as $file) {
                if (
                    str_ends_with($file, '/Resource.php') ||
                    str_ends_with($file, '/ImportConditionDto.php')
                ) {
                    continue;
                }
                $hookClass = 'App\\resources\\' . basename($file, '.php');
                if (class_exists($hookClass) && $hookClass::supports()) {
                    /** @var Resource $resource */
                    $resource = $hookClass::getInstance();
                    $mandatoryFields = $resource->getMandatoryFields();
                    foreach ($resource->getImportCondition() as $importCondition) {
                        $mandatoryFields[] = $importCondition->getField();
                    }
                    $resource->setMandatoryFields($mandatoryFields);
                    $resources[] = $resource;
                }
            }
            $this->resources = $resources;
        }
        return $this->resources;
    }

    public function getFieldsForEntity(Resource $resource, bool $withMetadata = true): array
    {
        $transDomain = $resource->getTransDomain();
        $typeModel = GraphqlService::getInstance()->getTypeModel($resource->getTypeModel());
        if (!is_null($typeModel)) {
            $fieldsObject = array_filter($typeModel,
                static fn(stdClass $entity): bool => $entity->type->kind !== 'OBJECT' &&
                    $entity->type->kind !== 'LIST' &&
                    $entity->type->ofType?->kind !== 'LIST');
        } else {
            $fieldsObject = [];
        }

        $trans = SageTranslationUtils::getTranslations();
        $objectFields = [];
        foreach ($fieldsObject as $fieldObject) {
            $v = $trans[$transDomain][$fieldObject->name];
            $objectFields[$fieldObject->name] = $v['label'] ?? $v;
        }

        // region custom meta fields
        if ($withMetadata) {
            foreach ($resource->getMetadata()() as $metadata) {
                if (!$metadata->getShowInOptions()) {
                    continue;
                }
                $fieldName = Sage::META_DATA_PREFIX . $metadata->getField();
                $objectFields[$fieldName] = $trans[$transDomain][$fieldName];
            }
        }
        // endregion

        return $objectFields;
    }

    public function getArRef(int $postId): mixed
    {
        return get_post_meta($postId, FArticleResource::META_KEY, true);
    }

    public function addSelectionSetAsMetadata(array $selectionSets, array &$sageEntityMetadatas, ?stdClass $obj, string $prefix = ''): array
    {
        foreach ($selectionSets as $subEntity => $selectionSet) {
            if (is_array($selectionSet) && array_key_exists('name', $selectionSet)) {
                $sageEntityMetadatas[] = new SageEntityMetadata(field: '_' . $prefix . $selectionSet['name'], value: static function (StdClass $entity) use ($selectionSet, $prefix) {
                    return PathUtils::getByPath($entity, $prefix)->{$selectionSet['name']};
                });
            } else if (!is_null($obj) && $selectionSet instanceof ArgumentSelectionSetDto) {
                foreach ($obj->{$subEntity} as $subObject) {
                    $this->addSelectionSetAsMetadata(
                        $selectionSet->getSelectionSet(),
                        $sageEntityMetadatas,
                        $subObject,
                        $subEntity . '[' . $subObject->{$selectionSet->getKey()} . '].'
                    );
                }
            }
        }
        return $sageEntityMetadatas;
    }

    public function populateMetaDatas(?array $data, array $fields, Resource $resource): array|null
    {
        if (empty($data)) {
            return $data;
        }
        $entityName = $resource->getEntityName();
        $fieldNames = array_map(static function (array $field) {
            return str_replace(Sage::PREFIX_META_DATA, '', $field['name']);
        }, array_filter($fields, static function (array $field) {
            return str_starts_with($field['name'], Sage::PREFIX_META_DATA);
        }));
        $mandatoryField = $resource->getMandatoryFields()[0];
        $getIdentifier = $resource->getGetIdentifier();
        if (is_null($getIdentifier)) {
            $getIdentifier = static function (array $entity) use ($mandatoryField) {
                return $entity[$mandatoryField];
            };
        }
        $ids = array_map($getIdentifier, $data["data"][$entityName]["items"]);

        $metaKeyIdentifier = $resource->getMetaKeyIdentifier();
        $metaTable = $resource->getMetaTable();
        $metaColumnIdentifier = $resource->getMetaColumnIdentifier();
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
                return $field['name'] === Sage::META_DATA_PREFIX . '_postId';
            }) !== [];
        $mapping = array_flip($mapping);
        $canImport = $resource->getCanImport();
        $postUrl = $resource->getPostUrl();
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
}
