<?php

namespace App\utils;

if (!defined('ABSPATH')) {
    exit;
}

final class ResourceUtils
{
    public const FCOMPTET_ENTITY_NAME = 'fComptets';
    public const FCOMPTET_TYPE_MODEL = 'FComptet';
    public const FCOMPTET_DEFAULT_SORT = 'ctNum';
    public const FCOMPTET_FILTER_TYPE = 'FComptetFilterInput';
    public const PEXPEDITION_ENTITY_NAME = 'pExpeditions';
    public const PEXPEDITION_TYPE_MODEL = 'PExpedition';
    public const FDOCENTETE_ENTITY_NAME = 'fDocentetes';
    public const FDOCENTETE_TYPE_MODEL = 'FDocentete';
    public const FDOCENTETE_DEFAULT_SORT = 'doDate';
    public const FDOCENTETE_FILTER_TYPE = 'FDocenteteFilterInput';
    public const FDOCLIGNE_ENTITY_NAME = 'fDoclignes';
    public const FARTICLE_ENTITY_NAME = 'fArticles';
    public const FARTICLE_TYPE_MODEL = 'FArticle';
    public const FARTICLE_TYPE_TABLE = 'F_ARTICLE';
    public const FARTICLE_DEFAULT_SORT = 'arRef';
    public const FARTICLE_FILTER_TYPE = 'FArticleFilterInput';
    public const PCATTARIF_ENTITY_NAME = 'pCattarifs';
    public const PCATTARIF_TYPE_MODEL = 'PCattarif';
    public const FPAYS_ENTITY_NAME = 'fPays';
    public const FPAYS_TYPE_MODEL = 'FPay';
    public const FTAXES_TYPE_MODEL = 'FTaxe';
    public const FTAXES_ENTITY_NAME = 'fTaxes';
    public const PCATCOMPTA_ENTITY_NAME = 'pCatcomptas';
    public const PCATCOMPTA_TYPE_MODEL = 'PCatcompta';
    public const PDOSSIER_ENTITY_NAME = 'pDossiers';
    public const FFAMILLE_ENTITY_NAME = 'fFamilles';
    public const PUNITE_ENTITY_NAME = 'pUnites';
    public const FCATALOGUE_ENTITY_NAME = 'fCatalogues';
    public const FGLOSSAIRE_ENTITY_NAME = 'fGlossaires';
    public const CBSYSLIBRE_ENTITY_NAME = 'cbSysLibres';
    public const FDEPOT_ENTITY_NAME = 'fDepots';
    public const PPREFERENCE_ENTITY_NAME = 'pPreferences';
}
