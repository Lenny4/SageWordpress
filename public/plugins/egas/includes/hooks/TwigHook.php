<?php

namespace App\hooks;

use App\Sage;
use App\utils\SageTranslationUtils;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigHook
{
    public Environment $twig;
    public string $dir;

    public function __construct()
    {
        $sage = Sage::getInstance();
        $templatesDir = __DIR__ . '/../../templates';
        $filesystemLoader = new FilesystemLoader($templatesDir);
        $twigOptions = [
            'debug' => WP_DEBUG,
        ];
        if (!WP_DEBUG) {
            $twigOptions['cache'] = $templatesDir . '/cache';
        }

        $this->twig = new Environment($filesystemLoader, $twigOptions);
        if (WP_DEBUG) {
            // https://twig.symfony.com/doc/3.x/functions/dump.html
            $this->twig->addExtension(new DebugExtension());
        }
        $this->dir = dirname($sage->file);
//        $this->twig->addExtension(new IntlExtension());
        $this->registerFunction();
        $this->registerFilter();
    }

    private function registerFunction(): void
    {
        $this->twig->addFunction(new TwigFunction('getTranslations', static fn(): array => SageTranslationUtils::getTranslations()));
        $this->twig->addFunction(new TwigFunction('get_locale', static fn(): string => substr(get_locale(), 0, 2)));
        $this->twig->addFunction(new TwigFunction('getAllFilterType', static function (): array {
            $r = [];
            foreach ([
                         'StringOperationFilterInput',
                         'IntOperationFilterInput',
                         'ShortOperationFilterInput',
                         'DecimalOperationFilterInput',
                         'DateTimeOperationFilterInput',
                         'UuidOperationFilterInput',
                     ] as $f) {
                switch ($f) {
                    case 'StringOperationFilterInput':
                        $r[$f] = [
                            'contains',
                            'endsWith',
                            'eq',
                            'in',
                            'ncontains',
                            'nendsWith',
                            'neq',
                            'nin',
                            'nstartsWith',
                            'startsWith',
                        ];
                        break;
                    case 'IntOperationFilterInput':
                    case 'ShortOperationFilterInput':
                    case 'DecimalOperationFilterInput':
                    case 'DateTimeOperationFilterInput':
                    case 'UuidOperationFilterInput':
                        $r[$f] = [
                            'eq',
                            'gt',
                            'gte',
                            'in',
                            'lt',
                            'lte',
                            'neq',
                            'ngt',
                            'ngte',
                            'nin',
                            'nlt',
                            'nlte',
                        ];
                        break;
                }
            }

            return $r;
        }));
        $this->twig->addFunction(new TwigFunction('getPaginationRange', static fn(): array => Sage::$paginationRange));
        $this->twig->addFunction(new TwigFunction('get_site_url', static fn() => get_site_url()));
        $this->twig->addFunction(new TwigFunction('getUrlWithParam', static function (string $paramName, int|string $v): string|array|null {
            $url = $_SERVER['REQUEST_URI'];
            if (str_contains($url, $paramName)) {
                $url = preg_replace('/' . $paramName . '=([^&]*)/', $paramName . '=' . $v, $url);
            } else {
                $url .= '&' . $paramName . '=' . $v;
            }

            return $url;
        }));
        // todo remove
        $this->twig->addFunction(new TwigFunction('getSortData', static function (array $queryParams): array {
            [$sortField, $sortValue] = SageGraphQl::getSortField($queryParams);

            if ($sortValue === 'asc') {
                $otherSort = 'desc';
            } else {
                $sortValue = 'desc';
                $otherSort = 'asc';
            }

            return [
                'sortValue' => $sortValue,
                'otherSort' => $otherSort,
                'sortField' => $sortField,
            ];
        }));
        $this->twig->addFunction(new TwigFunction('file_exists', static fn(string $path): bool => file_exists($this->dir . '/' . $path)));
        $this->twig->addFunction(new TwigFunction('get_option', static fn(string $option): string => get_option($option)));
        // todo
//        $this->twig->addFunction(new TwigFunction('getPricesProduct', static function (WC_Product $product) use ($sageWoocommerce): array {
//            $r = $sageWoocommerce->getPricesProduct($product);
//            foreach ($r as &$r1) {
//                foreach ($r1 as &$r2) {
//                    $r2 = (array)$r2;
//                }
//            }
//            return $r;
//        }));
        $this->twig->addFunction(new TwigFunction('get_woocommerce_currency_symbol', static function (): string {
            return html_entity_decode(get_woocommerce_currency_symbol());
        }));
        $this->twig->addFunction(new TwigFunction('get_woocommerce_currency', static function (): string {
            return get_woocommerce_currency();
        }));
        $this->twig->addFunction(new TwigFunction('order_get_currency', static function (): string {
            return html_entity_decode(get_woocommerce_currency_symbol());
        }));
        $this->twig->addFunction(new TwigFunction('show_taxes_change', static function (array $taxes): string {
            return implode(' | ', array_map(static function (array $taxe) {
                return $taxe['code'] . ' => ' . $taxe['amount'];
            }, $taxes));
        }));
        $this->twig->addFunction(new TwigFunction('getDoTypes', static function (array $fDoclignes): array {
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
        $this->twig->addFunction(new TwigFunction('formatFDoclignes', static function (array $fDoclignes, array $doTypes): array {
            usort($fDoclignes, static function (stdClass $a, stdClass $b) use ($doTypes) {
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
        $this->twig->addFunction(new TwigFunction('getProductChangeLabel', static function (stdClass $productChange, array $products) {
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
        $this->twig->addExtension(new IntlExtension());
        $this->twig->addFunction(new TwigFunction('flattenAllTranslations', static function (array $allTranslations): array {
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
        $this->twig->addFunction(new TwigFunction('getFilterInput', static function (array $fields, string $prop) {
            foreach ($fields as $field) {
                if ($field['name'] === $prop) {
                    return $field['type'];
                }
            }
            return null;
        }));
        $this->twig->addFunction(new TwigFunction('get_admin_url', static function (): string {
            return get_admin_url();
        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('getDefaultFilters', static function () use ($settings): array {
//            return array_map(static function (SageEntityMenu $sageEntityMenu) {
//                $entityName = $sageEntityMenu->getEntityName();
//                return [
//                    'entityName' => Sage::TOKEN . '_' . $entityName,
//                    'value' => get_option(Sage::TOKEN . '_default_filter_' . $entityName, null),
//                ];
//            }, $settings->sageEntityMenus);
//        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('getFDoclignes', static function (array|null|string $fDocentetes) use ($sageWoocommerce): array {
//            return $sageWoocommerce->getFDoclignes($fDocentetes);
//        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('getMainFDocenteteOfExtendedFDocentetes', static function (WC_Order $order, array|null|string $fDocentetes) use ($sageWoocommerce): stdClass|null|string {
//            return $sageWoocommerce->getMainFDocenteteOfExtendedFDocentetes($order, $fDocentetes);
//        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('getFDocentete', static function (array $fDocentetes, string $doPiece, int $doType) use ($sageWoocommerce): stdClass|null|string {
//            $fDocentete = current(array_filter($fDocentetes, static function (stdClass $fDocentete) use ($doPiece, $doType) {
//                return $fDocentete->doPiece === $doPiece && $fDocentete->doType === $doType;
//            }));
//            if ($fDocentete !== false) {
//                return $fDocentete;
//            }
//            return null;
//        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('canUpdateUserOrFComptet', static function (array $fComptet) use ($sage): array {
//            return $sage->canUpdateUserOrFComptet(json_decode(json_encode($fComptet), false));
//        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('canImportFArticle', static function (array $fArticle) use ($sageWoocommerce): array {
//            return $sageWoocommerce->canImportFArticle(json_decode(json_encode($fArticle), false));
//        }));
        // todo
//        $this->twig->addFunction(new TwigFunction('canImportOrderFromSage', static function (array $fDocentete) use ($sageWoocommerce): array {
//            return $sageWoocommerce->canImportOrderFromSage(json_decode(json_encode($fDocentete), false));
//        }));
        $this->twig->addFunction(new TwigFunction('getToken', static function (): string {
            return Sage::TOKEN;
        }));
    }

    private function registerFilter(): void
    {
        $this->twig->addFilter(new TwigFilter('trans', static fn(string $string) => __($string, Sage::TOKEN)));
        $this->twig->addFilter(new TwigFilter('esc_attr', static fn(string $string) => esc_attr($string)));
        $this->twig->addFilter(new TwigFilter('selected', static fn(bool $selected) => selected($selected, true, false)));
        $this->twig->addFilter(new TwigFilter('disabled', static fn(bool $disabled) => disabled($disabled, true, false)));
        $this->twig->addFilter(new TwigFilter('bytesToString', static fn(array $bytes): string => implode('', array_map("chr", $bytes))));
        $this->twig->addFilter(new TwigFilter('wp_nonce_field', static fn(string $action) => wp_nonce_field($action)));
        $this->twig->addFilter(new TwigFilter('wp_create_nonce', static fn(string $action) => wp_create_nonce($action)));
        $this->twig->addFilter(new TwigFilter('sortByFields', static function (array $item, array $fields): array {
            uksort($item, static function (string $a, string $b) use ($fields): int {
                $fieldsOrder = [];
                foreach ($fields as $i => $f) {
                    $fieldsOrder[str_replace(SageSettings::PREFIX_META_DATA, '', $f['name'])] = $i;
                }

                return $fieldsOrder[$a] <=> $fieldsOrder[$b];
            });
            return $item;
        }));
        $this->twig->addFilter(new TwigFilter('json_decode', static fn(string $string): mixed => json_decode(stripslashes($string), true, 512, JSON_THROW_ON_ERROR)));
        $this->twig->addFilter(new TwigFilter('gettype', static fn(mixed $value): string => gettype($value)));
        // todo remove
        $this->twig->addFilter(new TwigFilter('removeFields', static fn(array $fields, array $hideFields): array => array_values(array_filter($fields, static fn(array $field): bool => !in_array($field["name"], $hideFields)))));
        $this->twig->addFilter(new TwigFilter('sortInsensitive', static function (array $array): array {
            uasort($array, 'strnatcasecmp');
            return $array;
        }));
        $this->twig->addFilter(new TwigFilter('getEntityIdentifier', static function (array $obj, array $mandatoryFields): string {
            $r = [];
            foreach ($mandatoryFields as $mandatoryField) {
                $r[] = $obj[str_replace(SageSettings::PREFIX_META_DATA, '', $mandatoryField)];
            }

            return implode('|', $r);
        }));
        $this->twig->addFilter(new TwigFilter('wpDate', static function (string $date): string {
            return date_i18n(wc_date_format(), strtotime($date)) . ' ' . date_i18n(wc_time_format(), strtotime($date));
        }));
    }
}
