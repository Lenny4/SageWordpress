export interface FArticleClientInterface {
  acCategorie: number;
  acPrixVen: number;
  acCoef: number;
  acPrixTtc?: number;
  ctNum?: string;
  acRemise: number;
  acTypeRem: boolean;
  acQteMont: number;
}

export interface PCattarifInterface {
  cbIndice: number;
  ctIntitule: string;
}

export interface FCatalogueInterface {
  clNo: number;
  clNiveau: number;
  clNoParent: number;
  clIntitule: string;
}

export interface FGlossaireInterface {
  glNo: number;
  glDomaine: number; // 0 -> Article, 1 => document
  glIntitule: string;
  glText: string;
}

export interface FArtglosseInterface {
  glNo: number;
  glNoNavigation: FGlossaireInterface;
}

export interface FArtstockInterface {
  deNo: number;
  asQteMini: number;
  asQteMaxi: number;
  asPrincipal: number;
}

export interface FDepotInterface {
  deNo: number;
  deIntitule: string;
}

export interface FArtfournisseInterface {
  ctNum: string;
  afRefFourniss: string;
  afPrincipal: number;
  afPrixAch: number;
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
