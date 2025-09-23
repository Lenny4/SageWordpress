<?php

namespace App\services;

use App\resources\FComptetResource;
use App\resources\FDocenteteResource;
use App\Sage;
use App\Utils\OrderUtils;
use stdClass;
use WC_Order;
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
            $wordpressService = WordpressService::getInstance();
            $ctNum = $wordpressService->getUserWordpressIdForSage($mailExistsUserId);
            if (!empty($ctNum)) {
                return [null, "<div class='notice notice-error is-dismissible'>" . __('This email address [' . $email . '] is already registered for user id: ' . $mailExistsUserId . '.', 'woocommerce') . "</div>", null];
            }
            $userId = $mailExistsUserId;
            $wordpressService->updateUserOrFComptet($ctNum, $userId, $fComptet);
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
        $sageEntityMenu = FComptetResource::getInstance();
        foreach ($sageEntityMenu->metadata as $metadata) {
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
}
