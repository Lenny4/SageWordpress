export interface FArticleClientInterface {
  acCategorie: number;
  acPrixVen: number;
  acCoef: number;
  acPrixTtc: number;
  ctNum: string;
  acRemise: number;
}

export interface FArticlePriceInterface {
  priceHt: number;
  priceTtc: number;
  taxes: TaxeInterface[];
  nCatCompta: NCatComptaInterface;
  nCatTarif: NCatTarifInterface;
}

interface NCatComptaInterface {
  cbIndice: number;
}

interface NCatTarifInterface {
  cbIndice: number;
  ctPrixTtc: number;
}

interface TaxeInterface {
  amount: number;
  taxeNumber: number;
  fTaxe: FTaxeInterface;
}

interface FTaxeInterface {
  taCode: string;
  taIntitule: string;
  taNp: number;
  taTaux: number;
  taTtaux: number;
}
