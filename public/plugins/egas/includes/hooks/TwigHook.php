<?php

namespace App\hooks;

use App\resources\Resource;
use App\Sage;
use App\services\SageService;
use App\services\TwigService;
use App\utils\FDocenteteUtils;
use App\utils\SageTranslationUtils;
use stdClass;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use WC_Order;
use WC_Product;

class TwigHook
{
    public function __construct()
    {
        add_action('init', function (): void {
            $twig = TwigService::getInstance();
            if ($twig->register) {
                return;
            }
            $twig->twig->addExtension(new IntlExtension());
            $this->registerFunction();
            $this->registerFilter();
            $twig->register = true;
        });
    }

    private function registerFunction(): void
    {
        $twig = TwigService::getInstance()->twig;
        $twig->addFunction(new TwigFunction('getTranslations', static fn(): array => SageTranslationUtils::getTranslations()));
        $twig->addFunction(new TwigFunction('get_locale', static fn(): string => substr(get_locale(), 0, 2)));
        $twig->addFunction(new TwigFunction('getAllFilterType', static fn(): array => SageService::getInstance()->getAllFilterType()));
        $twig->addFunction(new TwigFunction('getPaginationRange', static fn(): array => Sage::$paginationRange));
        $twig->addFunction(new TwigFunction('get_site_url', static fn() => get_site_url()));
        $twig->addFunction(new TwigFunction('get_option', static fn(string $option): string => get_option($option)));
        $twig->addFunction(new TwigFunction('get_woocommerce_currency_symbol', static fn(): string => html_entity_decode(get_woocommerce_currency_symbol())));
        $twig->addFunction(new TwigFunction('get_woocommerce_currency', static fn(): string => get_woocommerce_currency()));
        $twig->addFunction(new TwigFunction('order_get_currency', static fn(): string => html_entity_decode(get_woocommerce_currency_symbol())));
        $twig->addFunction(new TwigFunction('show_taxes_change', static fn(array $taxes): string => implode(' | ', array_map(static fn(array $taxe): string => $taxe['code'] . ' => ' . $taxe['amount'], $taxes))));
        $twig->addFunction(new TwigFunction('getDoTypes', static function (array $fDoclignes): array {
            $result = [];
            foreach ($fDoclignes as $fDocligne) {
                $result[$fDocligne->doType] = '';
                foreach (FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE as $doType => $field) {
                    if (!empty($fDocligne->{'dlPiece' . $field})) {
                        $result[$doType] = '';
                    }
                }
            }
            $result = array_keys($result);
            sort($result);
            return $result;
        }));
        $twig->addFunction(new TwigFunction('formatFDoclignes', static function (array $fDoclignes, array $doTypes): array {
            usort($fDoclignes, static function (stdClass $a, stdClass $b) use ($doTypes): int {
                foreach ($doTypes as $doType) {
                    if ($a->doType === $doType) {
                        $doPieceA = $a->doPiece;
                    } else {
                        $doPieceA = $a->{'dlPiece' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                    }
                    if ($b->doType === $doType) {
                        $doPieceB = $b->doPiece;
                    } else {
                        $doPieceB = $b->{'dlPiece' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                    }
                    if ($doPieceA !== $doPieceB) {
                        return strcmp($doPieceB, $doPieceA);
                    }
                    return $a->dlLigne <=> $b->dlLigne;
                }
                return 0;
            });
            $nbFDoclignes = count($fDoclignes);
            foreach ($fDoclignes as $fDocligne) {
                $fDocligne->display = [];
                foreach ($doTypes as $doType) {
                    if ($fDocligne->doType === $doType) {
                        $doPiece = $fDocligne->doPiece;
                        $dlQte = (int)$fDocligne->dlQte;
                    } else {
                        $doPiece = $fDocligne->{'dlPiece' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                        $dlQte = (int)$fDocligne->{'dlQte' . FDocenteteUtils::FDOCLIGNE_MAPPING_DO_TYPE[$doType]};
                    }
                    $fDocligne->display[$doType] = [
                        'doPiece' => $doPiece,
                        'doType' => $doType,
                        'dlQte' => $dlQte,
                        'prevDoPiece' => '',
                        'nextDoPiece' => '',
                    ];
                }
            }
            foreach ($doTypes as $indexDoType => $doType) {
                foreach ($fDoclignes as $i => $fDocligne) {
                    foreach (['prev' => -1, 'next' => +1] as $f => $v) {
                        $y = $i + $v;
                        while (
                            (
                                ($y > 0 && $v === -1) ||
                                ($y < $nbFDoclignes - 1 && $v === 1)
                            ) &&
                            (
                                $fDoclignes[$y]->display[$doType]['doPiece'] === ''
                            )
                        ) {
                            $y += $v;
                        }
                        if ($i !== $y && $y >= 0 && $y < $nbFDoclignes) {
                            $fDocligne->display[$doType][$f . 'DoPiece'] = $fDoclignes[$y]->display[$doType]['doPiece'];
                        }
                    }
                    $doPiece = $fDocligne->display[$doType]["doPiece"];
                    $prevDoPiece = $fDocligne->display[$doType]["prevDoPiece"];
                    $nextDoPiece = $fDocligne->display[$doType]["nextDoPiece"];
                    $fDocligne->display[$doType]['showBorderBottom'] = $doPiece !== '' && $doPiece !== $nextDoPiece;
                    $fDocligne->display[$doType]['showBorderX'] = $doPiece !== '' || $prevDoPiece === $nextDoPiece;
                    $fDocligne->display[$doType]['showDoPiece'] = !empty($doPiece) && ($doPiece !== $prevDoPiece);
                    $fDocligne->display[$doType]['showArrow'] =
                        $indexDoType > 0 &&
                        $doPiece !== '' &&
                        array_key_exists($doTypes[$indexDoType - 1], $fDocligne->display) &&
                        $fDocligne->display[$doTypes[$indexDoType - 1]]["doPiece"] !== '';
                }
            }

            return $fDoclignes;
        }));
        $twig->addFunction(new TwigFunction('getProductChangeLabel', static function (stdClass $productChange, array $products) {
            if (!array_key_exists($productChange->postId, $products)) {
                if (!empty($productChange->fDocligneLabel)) {
                    return $productChange->fDocligneLabel;
                }
                return 'undefined';
            }
            /** @var WC_Product $p */
            $p = $products[$productChange->postId];
            return $p->get_name();
        }));
        $twig->addFunction(new TwigFunction('flattenAllTranslations', static function (array $allTranslations): array {
            $flatten = function (array $values, array &$result = []) use (&$flatten) {
                foreach ($values as $key => $value) {
                    if (is_array($value)) {
                        $flatten($value, $result);
                    } else {
                        $result[$key] = $value;
                    }
                }
                return $result;
            };
            foreach ($allTranslations as $key => $allTranslation) {
                if (
                    is_array($allTranslation) &&
                    array_key_exists('values', $allTranslation) &&
                    is_array($allTranslation['values'])
                ) {
                    $allTranslations[$key]['values'] = $flatten($allTranslation['values']);
                }
            }

            return $allTranslations;
        }));
        $twig->addFunction(new TwigFunction('get_admin_url', static fn(): string => get_admin_url()));
        $twig->addFunction(new TwigFunction('getDefaultFilters', static fn(): array => array_map(static function (Resource $resource): array {
            $entityName = $resource->getEntityName();
            return [
                'entityName' => Sage::TOKEN . '_' . $entityName,
                'value' => get_option(Sage::TOKEN . '_default_filter_' . $entityName, null),
            ];
        }, SageService::getInstance()->getResources())));
        $twig->addFunction(new TwigFunction('getFDoclignes', static fn(array|null|string $fDocentetes): array => SageService::getInstance()->getFDoclignes($fDocentetes)));
        $twig->addFunction(new TwigFunction('getMainFDocenteteOfExtendedFDocentetes', static fn(WC_Order $order, array|null|string $fDocentetes): stdClass|null|string => SageService::getInstance()->getMainFDocenteteOfExtendedFDocentetes($order, $fDocentetes)));
        $twig->addFunction(new TwigFunction('getFDocentete', static function (array $fDocentetes, string $doPiece, int $doType): stdClass|null|string {
            $fDocentete = current(array_filter($fDocentetes, static fn(stdClass $fDocentete): bool => $fDocentete->doPiece === $doPiece && $fDocentete->doType === $doType));
            if ($fDocentete !== false) {
                return $fDocentete;
            }
            return null;
        }));
        $twig->addFunction(new TwigFunction('getToken', static fn(): string => Sage::TOKEN));
    }

    private function registerFilter(): void
    {
        $twig = TwigService::getInstance()->twig;
        $twig->addFilter(new TwigFilter('trans', static fn(string $string) => __($string, Sage::TOKEN)));
        $twig->addFilter(new TwigFilter('esc_attr', static fn(string $string) => esc_attr($string)));
        $twig->addFilter(new TwigFilter('wp_create_nonce', static fn(string $action) => wp_create_nonce($action)));
        $twig->addFilter(new TwigFilter('gettype', static fn(mixed $value): string => gettype($value)));
    }
}
