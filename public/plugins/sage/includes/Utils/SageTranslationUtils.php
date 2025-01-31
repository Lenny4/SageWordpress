<?php

namespace App\Utils;

use App\Sage;
use App\SageSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class SageTranslationUtils
{
    public const TRANS_FCOMPTETS = 'fComptets';

    public const TRANS_FDOCENTETES = 'fDocentetes';

    public const TRANS_FARTICLES = 'fArticles';

    public static function getTranslations(): array
    {
        return [
            self::TRANS_FCOMPTETS => [
                "ctNum" => __("Numéro de compte", 'sage'),
                "ctIntitule" => __("Intitulé du tiers", 'sage'),
                "ctType" => __("Type de tiers", 'sage'),
                "cgNumPrinc" => __("cgNumPrinc", 'sage'),
                "ctQualite" => __("Qualité", 'sage'),
                "ctClassement" => __("Classement", 'sage'),
                "ctContact" => __("Contact", 'sage'),
                "ctAdresse" => __("Adresse", 'sage'),
                "ctComplement" => __("ctComplement", 'sage'),
                "ctCodePostal" => __("Code postal", 'sage'),
                "ctVille" => __("Ville", 'sage'),
                "ctCodeRegion" => __("Code region", 'sage'),
                "ctPays" => __("Pays", 'sage'),
                "ctPaysCode" => __("Code pays", 'sage'),
                "ctRaccourci" => __("Raccourci", 'sage'),
                "btNum" => __("btNum", 'sage'),
                "nDevise" => __("nDevise", 'sage'),
                "ctApe" => __("Code APE (NAF)", 'sage'),
                "ctIdentifiant" => __("Numéro identifiant", 'sage'),
                "ctSiret" => __("Siret", 'sage'),
                "ctStatistique01" => __("ctStatistique01", 'sage'),
                "ctStatistique02" => __("ctStatistique02", 'sage'),
                "ctStatistique03" => __("ctStatistique03", 'sage'),
                "ctStatistique04" => __("ctStatistique04", 'sage'),
                "ctStatistique05" => __("ctStatistique05", 'sage'),
                "ctStatistique06" => __("ctStatistique06", 'sage'),
                "ctStatistique07" => __("ctStatistique07", 'sage'),
                "ctStatistique08" => __("ctStatistique08", 'sage'),
                "ctStatistique09" => __("ctStatistique09", 'sage'),
                "ctStatistique10" => __("ctStatistique10", 'sage'),
                "ctCommentaire" => __("Commentaire", 'sage'),
                "ctEncours" => __("Encours maximum autorisé", 'sage'),
                "ctAssurance" => __("Plafond assurance crédit", 'sage'),
                "ctNumPayeur" => __("ctNumPayeur", 'sage'),
                "nRisque" => __("nRisque", 'sage'),
                "nCatTarif" => __("nCatTarif", 'sage'),
                "ctTaux01" => __("ctTaux01", 'sage'),
                "ctTaux02" => __("ctTaux02", 'sage'),
                "ctTaux03" => __("ctTaux03", 'sage'),
                "ctTaux04" => __("ctTaux04", 'sage'),
                "nCatCompta" => __("nCatCompta", 'sage'),
                "nPeriod" => __("nPeriod", 'sage'),
                "ctFacture" => __("Nombre de factures", 'sage'),
                "ctBlfact" => __("1 BL/Facture", 'sage'),
                "ctLangue" => __("Langue", 'sage'),
                "nExpedition" => __("nExpedition", 'sage'),
                "nCondition" => __("nCondition", 'sage'),
                "ctSaut" => __("ctSaut", 'sage'),
                "ctLettrage" => __("Lettrage automatique (True) ou non (False).", 'sage'),
                "ctValidEch" => __("ctValidEch", 'sage'),
                "ctSommeil" => __("Mis en sommeil (True) ou actif (False)", 'sage'),
                "deNo" => __("deNo", 'sage'),
                "cbDeNo" => __("cbDeNo", 'sage'),
                "ctControlEnc" => __("ctControlEnc", 'sage'),
                "ctNotRappel" => __("ctNotRappel", 'sage'),
                "nAnalytique" => __("nAnalytique", 'sage'),
                "cbNAnalytique" => __("cbNAnalytique", 'sage'),
                "caNum" => __("Numéro", 'sage'),
                "ctTelephone" => __("Téléphone", 'sage'),
                "ctTelecopie" => __("Télécopie", 'sage'),
                "ctEmail" => __("Email", 'sage'),
                "ctSite" => __("Site", 'sage'),
                "ctCoface" => __("Numéro Coface SCRL", 'sage'),
                "ctSurveillance" => __("Placé sous surveillance (True) ou non (False).", 'sage'),
                "ctSvDateCreate" => __("ctSvDateCreate", 'sage'),
                "ctSvFormeJuri" => __("ctSvFormeJuri", 'sage'),
                "ctSvEffectif" => __("ctSvEffectif", 'sage'),
                "ctSvCa" => __("ctSvCa", 'sage'),
                "ctSvResultat" => __("ctSvResultat", 'sage'),
                "ctSvIncident" => __("ctSvIncident", 'sage'),
                "ctSvDateIncid" => __("ctSvDateIncid", 'sage'),
                "ctSvPrivil" => __("ctSvPrivil", 'sage'),
                "ctSvRegul" => __("ctSvRegul", 'sage'),
                "ctSvCotation" => __("ctSvCotation", 'sage'),
                "ctSvDateMaj" => __("ctSvDateMaj", 'sage'),
                "ctSvObjetMaj" => __("ctSvObjetMaj", 'sage'),
                "ctSvDateBilan" => __("ctSvDateBilan", 'sage'),
                "ctSvNbMoisBilan" => __("ctSvNbMoisBilan", 'sage'),
                "nAnalytiqueIfrs" => __("nAnalytiqueIfrs", 'sage'),
                "cbNAnalytiqueIfrs" => __("cbNAnalytiqueIfrs", 'sage'),
                "caNumIfrs" => __("caNumIfrs", 'sage'),
                "ctPrioriteLivr" => __("ctPrioriteLivr", 'sage'),
                "ctLivrPartielle" => __("ctLivrPartielle", 'sage'),
                "mrNo" => __("mrNo", 'sage'),
                "cbMrNo" => __("cbMrNo", 'sage'),
                "ctNotPenal" => __("ctNotPenal", 'sage'),
                "ebNo" => __("ebNo", 'sage'),
                "cbEbNo" => __("cbEbNo", 'sage'),
                "ctNumCentrale" => __("ctNumCentrale", 'sage'),
                "cbProt" => __("cbProt", 'sage'),
                "cbMarq" => __("cbMarq", 'sage'),
                "cbCreateur" => __("cbCreateur", 'sage'),
                "cbModification" => __("cbModification", 'sage'),
                "cbReplication" => __("cbReplication", 'sage'),
                "cbFlag" => __("cbFlag", 'sage'),
                "rowguid" => __("rowguid", 'sage'),
                "coNo" => __("coNo", 'sage'),
                "cbCoNo" => __("cbCoNo", 'sage'),
                "ctDateFermeDebut" => __("ctDateFermeDebut", 'sage'),
                "ctDateFermeFin" => __("ctDateFermeFin", 'sage'),
                "ctFactureElec" => __("ctFactureElec", 'sage'),
                "ctTypeNif" => __("ctTypeNif", 'sage'),
                "ctRepresentInt" => __("ctRepresentInt", 'sage'),
                "ctRepresentNif" => __("ctRepresentNif", 'sage'),
                "ctEdiCodeType" => __("ctEdiCodeType", 'sage'),
                "ctEdiCode" => __("ctEdiCode", 'sage'),
                "ctEdiCodeSage" => __("ctEdiCodeSage", 'sage'),
                "ctProfilSoc" => __("ctProfilSoc", 'sage'),
                "ctStatutContrat" => __("ctStatutContrat", 'sage'),
                "ctDateMaj" => __("ctDateMaj", 'sage'),
                "ctEchangeRappro" => __("ctEchangeRappro", 'sage'),
                "ctEchangeCr" => __("ctEchangeCr", 'sage'),
                "piNoEchange" => __("piNoEchange", 'sage'),
                "cbPiNoEchange" => __("cbPiNoEchange", 'sage'),
                "ctBonApayer" => __("ctBonApayer", 'sage'),
                "ctDelaiTransport" => __("ctDelaiTransport", 'sage'),
                "ctDelaiAppro" => __("ctDelaiAppro", 'sage'),
                "ctLangueIso2" => __("ctLangueIso2", 'sage'),
                "ctAnnulationCr" => __("ctAnnulationCr", 'sage'),
                "ctFacebook" => __("Compte Facebook", 'sage'),
                "ctLinkedIn" => __("Compte LinkedIn", 'sage'),
                "ctExclureTrait" => __("ctExclureTrait", 'sage'),
                "ctGdpr" => __("ctGdpr", 'sage'),
                "ctProspect" => __("Client de type prospect", 'sage'),
                "cbCreation" => __("cbCreation", 'sage'),
                "cbCreationUser" => __("cbCreationUser", 'sage'),
                "ctOrderDay01" => __("ctOrderDay01", 'sage'),
                "ctOrderDay02" => __("ctOrderDay02", 'sage'),
                "ctOrderDay03" => __("ctOrderDay03", 'sage'),
                "ctOrderDay04" => __("ctOrderDay04", 'sage'),
                "ctOrderDay05" => __("ctOrderDay05", 'sage'),
                "ctOrderDay06" => __("ctOrderDay06", 'sage'),
                "ctOrderDay07" => __("ctOrderDay07", 'sage'),
                "ctDeliveryDay01" => __("ctDeliveryDay01", 'sage'),
                "ctDeliveryDay02" => __("ctDeliveryDay02", 'sage'),
                "ctDeliveryDay03" => __("ctDeliveryDay03", 'sage'),
                "ctDeliveryDay04" => __("ctDeliveryDay04", 'sage'),
                "ctDeliveryDay05" => __("ctDeliveryDay05", 'sage'),
                "ctDeliveryDay06" => __("ctDeliveryDay06", 'sage'),
                "ctDeliveryDay07" => __("ctDeliveryDay07", 'sage'),
                "calNo" => __("calNo", 'sage'),
                "cbCalNo" => __("cbCalNo", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_last_update' => __("Dernière synchronisation dans Wordpress", 'sage'),
                SageSettings::PREFIX_META_DATA . Sage::META_KEY_CT_NUM => __("Numéro de compte", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_nCatTarif' => __("Catégorie de tarif", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_nCatCompta' => __("Catégorie comptable", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_postId' => __("Wordpress ID", 'sage'),
            ],
            self::TRANS_FDOCENTETES => [
                "doDomaine" => [
                    'label' => __("Domaine (cf. énumérateur DomaineType).", 'sage'),
                    'values' => [
                        '0' => __("Vente", 'sage'),
                        '1' => __("Achat", 'sage'),
                        '2' => __("Stock", 'sage'),
                        // '3' => __("Ticket", 'sage'),
                        '4' => __("Interne", 'sage'),
                    ],
                ],
                "doType" => [
                    'label' => __("Type du document (cf. énumérateur DocumentType)", 'sage'),
                    'values' => [
                        __("Documents des ventes", 'sage') => [
                            '0' => __("Devis", 'sage'),
                            '1' => __("Bon de commande", 'sage'),
                            '2' => __("Préparation de livraison", 'sage'),
                            '3' => __("Bon de livraison", 'sage'),
                            '4' => __("Bon de retour", 'sage'),
                            '5' => __("Bon d'avoir financier", 'sage'),
                            '6' => __("Facture", 'sage'),
                            '7' => __("Facture comptabilisée", 'sage'),
                            '8' => __("Archive", 'sage'),
                        ],
                        __("Documents des achats", 'sage') => [
                            '10' => __("Demande d'achat", 'sage'),
                            '11' => __("Préparation commande", 'sage'),
                            '12' => __("Commande confirmée", 'sage'),
                            '13' => __("Livraison", 'sage'),
                            '14' => __("Reprise", 'sage'),
                            '15' => __("Avoir", 'sage'),
                            '16' => __("Facture", 'sage'),
                            '17' => __("Facture comptabilisée", 'sage'),
                            '18' => __("Archive", 'sage'),
                        ],
                        __("Documents des stocks", 'sage') => [
                            '20' => __("Mouvements d'entrée", 'sage'),
                            '21' => __("Mouvements de sortie", 'sage'),
                            '22' => __("Dépréciation du stock", 'sage'),
                            '23' => __("Virement dépôt à dépôt", 'sage'),
                            '24' => __("Préparation de fabrication", 'sage'),
                            '25' => __("Ordre de fabrication", 'sage'),
                            '26' => __("Bon de fabrication", 'sage'),
                            '27' => __("Archive", 'sage'),
                        ],
                        __("Documents internes", 'sage') => [
                            '40' => __("Document interne 1", 'sage'),
                            '41' => __("Document interne 2", 'sage'),
                            '42' => __("Document interne 3", 'sage'),
                            '43' => __("Document interne 4", 'sage'),
                            '44' => __("Document interne 5", 'sage'),
                            '45' => __("Document interne 6", 'sage'),
                            '46' => __("Document interne 7", 'sage'),
                            '47' => __("Document archive", 'sage'),
                        ],
                    ]
                ],
                "doPiece" => __("N° de pièce", 'sage'),
                "doDate" => __("Date", 'sage'),
                "doRef" => __("Référence", 'sage'),
                "doTiers" => __("doTiers", 'sage'),
                "doPeriod" => __("doPeriod", 'sage'),
                "doDevise" => __("doDevise", 'sage'),
                "doCours" => __("Cours", 'sage'),
                "deNo" => __("Dépôt", 'sage'),
                "cbDeNo" => __("Dépôt", 'sage'),
                "liNo" => __("liNo", 'sage'),
                "cbLiNo" => __("cbLiNo", 'sage'),
                "ctNumPayeur" => __("ctNumPayeur", 'sage'),
                "doExpedit" => __("doExpedit", 'sage'),
                "doNbFacture" => __("doNbFacture", 'sage'),
                "doBlfact" => __("Une facture par bon de livraison", 'sage'),
                "doTxEscompte" => __("doTxEscompte", 'sage'),
                "doReliquat" => __("Reliquat", 'sage'),
                "doImprim" => __("Document imprimé (True) ou non (False)", 'sage'),
                "caNum" => __("caNum", 'sage'),
                "doCoord01" => __("Coordonnées du document 01", 'sage'),
                "doCoord02" => __("Coordonnées du document 02", 'sage'),
                "doCoord03" => __("Coordonnées du document 03", 'sage'),
                "doCoord04" => __("Coordonnées du document 04", 'sage'),
                "doSouche" => __("doSouche", 'sage'),
                "doDateLivr" => __("doDateLivr", 'sage'),
                "doCondition" => __("doCondition", 'sage'),
                "doTarif" => __("doTarif", 'sage'),
                "doColisage" => __("Colisage", 'sage'),
                "doTypeColis" => __("doTypeColis", 'sage'),
                "doTransaction" => __("Transaction", 'sage'),
                "doLangue" => __("Langue (Cf. énumérateur LangueType)", 'sage'),
                "doEcart" => __("Ecart de la valorisation", 'sage'),
                "doRegime" => __("Régime", 'sage'),
                "nCatCompta" => __("nCatCompta", 'sage'),
                "doVentile" => __("doVentile", 'sage'),
                "abNo" => __("abNo", 'sage'),
                "doDebutAbo" => __("doDebutAbo", 'sage'),
                "doFinAbo" => __("doFinAbo", 'sage'),
                "doDebutPeriod" => __("doDebutPeriod", 'sage'),
                "doFinPeriod" => __("doFinPeriod", 'sage'),
                "cgNum" => __("cgNum", 'sage'),
                "doStatut" => __("Statut (Cf. énumérateur DocumentStatutType)", 'sage'),
                "doStatutString" => __("Statut", 'sage'),
                "doHeure" => __("Heure", 'sage'),
                "caNo" => __("caNo", 'sage'),
                "doTransfere" => __("Document transféré (True) ou non (False)", 'sage'),
                "doCloture" => __("Document clôturé", 'sage'),
                "doNoWeb" => __("doNoWeb", 'sage'),
                "doAttente" => __("doAttente", 'sage'),
                "doProvenance" => __("Provenance du document (cf. énumérateur DocumentProvenanceType)", 'sage'),
                "caNumIfrs" => __("caNumIfrs", 'sage'),
                "mrNo" => __("mrNo", 'sage'),
                "doTypeFrais" => __("doTypeFrais", 'sage'),
                "doValFrais" => __("doValFrais", 'sage'),
                "doTypeLigneFrais" => __("doTypeLigneFrais", 'sage'),
                "doTypeFranco" => __("doTypeFranco", 'sage'),
                "doValFranco" => __("doValFranco", 'sage'),
                "doTypeLigneFranco" => __("doTypeLigneFranco", 'sage'),
                "doTaxe1" => __("doTaxe1", 'sage'),
                "doTypeTaux1" => __("doTypeTaux1", 'sage'),
                "doTypeTaxe1" => __("doTypeTaxe1", 'sage'),
                "doTaxe2" => __("doTaxe2", 'sage'),
                "doTypeTaux2" => __("doTypeTaux2", 'sage'),
                "doTypeTaxe2" => __("doTypeTaxe2", 'sage'),
                "doTaxe3" => __("doTaxe3", 'sage'),
                "doTypeTaux3" => __("doTypeTaux3", 'sage'),
                "doTypeTaxe3" => __("doTypeTaxe3", 'sage'),
                "doMajCpta" => __("doMajCpta", 'sage'),
                "doMotif" => __("Motif de rectification (spécifique aux versions espagnoles)", 'sage'),
                "ctNumCentrale" => __("ctNumCentrale", 'sage'),
                "doContact" => __("Contact du document", 'sage'),
                "cbProt" => __("cbProt", 'sage'),
                "cbMarq" => __("cbMarq", 'sage'),
                "cbCreateur" => __("cbCreateur", 'sage'),
                "cbModification" => __("cbModification", 'sage'),
                "cbReplication" => __("cbReplication", 'sage'),
                "cbFlag" => __("cbFlag", 'sage'),
                "rowguid" => __("rowguid", 'sage'),
                "coNo" => __("coNo", 'sage'),
                "cbCoNo" => __("cbCoNo", 'sage'),
                "coNoCaissier" => __("coNoCaissier", 'sage'),
                "cbCoNoCaissier" => __("cbCoNoCaissier", 'sage'),
                "doFactureElec" => __("doFactureElec", 'sage'),
                "doTypeTransac" => __("doTypeTransac", 'sage'),
                "doDateLivrRealisee" => __("doDateLivrRealisee", 'sage'),
                "doDateExpedition" => __("doDateExpedition", 'sage'),
                "doFactureFrs" => __("doFactureFrs", 'sage'),
                "doPieceOrig" => __("doPieceOrig", 'sage'),
                "doGuid" => __("doGuid", 'sage'),
                "doEstatut" => __("doEstatut", 'sage'),
                "doDemandeRegul" => __("doDemandeRegul", 'sage'),
                "etNo" => __("etNo", 'sage'),
                "cbEtNo" => __("cbEtNo", 'sage'),
                "doValide" => __("Document validé", 'sage'),
                "doCoffre" => __("doCoffre", 'sage'),
                "doCodeTaxe1" => __("doCodeTaxe1", 'sage'),
                "doCodeTaxe2" => __("doCodeTaxe2", 'sage'),
                "doCodeTaxe3" => __("doCodeTaxe3", 'sage'),
                "doTotalHt" => __("doTotalHt", 'sage'),
                "doStatutBap" => __("doStatutBap", 'sage'),
                "doEscompte" => __("doEscompte", 'sage'),
                "doDocType" => __("doDocType", 'sage'),
                "doTypeCalcul" => __("doTypeCalcul", 'sage'),
                "doFactureFile" => __("doFactureFile", 'sage'),
                "cbHashVersion" => __("cbHashVersion", 'sage'),
                "cbHashDate" => __("cbHashDate", 'sage'),
                "cbHashOrder" => __("cbHashOrder", 'sage'),
                "cbCaNo" => __("cbCaNo", 'sage'),
                "cbCreation" => __("cbCreation", 'sage'),
                "cbCreationUser" => __("cbCreationUser", 'sage'),
                "doTotalHtnet" => __("Montant total HT Net", 'sage'),
                "doTotalTtc" => __("Montant total TTC", 'sage'),
                "doNetApayer" => __("Montant net à payer", 'sage'),
                "doMontantRegle" => __("Montant reglé du document", 'sage'),
                "doRefPaiement" => __("doRefPaiement", 'sage'),
                "doAdressePaiement" => __("doAdressePaiement", 'sage'),
                "doPaiementLigne" => __("doPaiementLigne", 'sage'),
                "doMotifDevis" => __("Motif devis perdus", 'sage'),
                "doConversion" => __("doConversion", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_postId' => __("Wordpress ID", 'sage'),
                "fraisExpedition" => __("Frais d'expedition", 'sage'),
                "dlMontantTaxe1" => __("dlMontantTaxe1", 'sage'),
                "dlMontantTaxe2" => __("dlMontantTaxe2", 'sage'),
                "dlMontantTaxe3" => __("dlMontantTaxe3", 'sage'),
            ],
            self::TRANS_FARTICLES => [
                "arRef" => __("arRef", 'sage'),
                "arDesign" => __("arDesign", 'sage'),
                "faCodeFamille" => __("faCodeFamille", 'sage'),
                "arSubstitut" => __("arSubstitut", 'sage'),
                "arRaccourci" => __("arRaccourci", 'sage'),
                "arGarantie" => __("arGarantie", 'sage'),
                "arUnitePoids" => __("arUnitePoids", 'sage'),
                "arPoidsNet" => __("arPoidsNet", 'sage'),
                "arPoidsBrut" => __("arPoidsBrut", 'sage'),
                "arUniteVen" => __("arUniteVen", 'sage'),
                "arPrixAch" => __("arPrixAch", 'sage'),
                "arCoef" => __("arCoef", 'sage'),
                "arPrixVen" => __("arPrixVen", 'sage'),
                "arPrixTtc" => __("arPrixTtc", 'sage'),
                "arGamme1" => __("arGamme1", 'sage'),
                "arGamme2" => __("arGamme2", 'sage'),
                "arSuiviStock" => __("arSuiviStock", 'sage'),
                "arNomencl" => __("arNomencl", 'sage'),
                "arStat01" => __("arStat01", 'sage'),
                "arStat02" => __("arStat02", 'sage'),
                "arStat03" => __("arStat03", 'sage'),
                "arStat04" => __("arStat04", 'sage'),
                "arStat05" => __("arStat05", 'sage'),
                "arEscompte" => __("arEscompte", 'sage'),
                "arDelai" => __("arDelai", 'sage'),
                "arHorsStat" => __("arHorsStat", 'sage'),
                "arVteDebit" => __("arVteDebit", 'sage'),
                "arNotImp" => __("arNotImp", 'sage'),
                "arSommeil" => __("arSommeil", 'sage'),
                "arLangue1" => __("arLangue1", 'sage'),
                "arLangue2" => __("arLangue2", 'sage'),
                "arCodeBarre" => __("arCodeBarre", 'sage'),
                "arCodeFiscal" => __("arCodeFiscal", 'sage'),
                "arPays" => __("arPays", 'sage'),
                "arFrais01FrDenomination" => __("arFrais01FrDenomination", 'sage'),
                "arFrais01FrRem01RemValeur" => __("arFrais01FrRem01RemValeur", 'sage'),
                "arFrais01FrRem01RemType" => __("arFrais01FrRem01RemType", 'sage'),
                "arFrais01FrRem02RemValeur" => __("arFrais01FrRem02RemValeur", 'sage'),
                "arFrais01FrRem02RemType" => __("arFrais01FrRem02RemType", 'sage'),
                "arFrais01FrRem03RemValeur" => __("arFrais01FrRem03RemValeur", 'sage'),
                "arFrais01FrRem03RemType" => __("arFrais01FrRem03RemType", 'sage'),
                "arFrais02FrDenomination" => __("arFrais02FrDenomination", 'sage'),
                "arFrais02FrRem01RemValeur" => __("arFrais02FrRem01RemValeur", 'sage'),
                "arFrais02FrRem01RemType" => __("arFrais02FrRem01RemType", 'sage'),
                "arFrais02FrRem02RemValeur" => __("arFrais02FrRem02RemValeur", 'sage'),
                "arFrais02FrRem02RemType" => __("arFrais02FrRem02RemType", 'sage'),
                "arFrais02FrRem03RemValeur" => __("arFrais02FrRem03RemValeur", 'sage'),
                "arFrais02FrRem03RemType" => __("arFrais02FrRem03RemType", 'sage'),
                "arFrais03FrDenomination" => __("arFrais03FrDenomination", 'sage'),
                "arFrais03FrRem01RemValeur" => __("arFrais03FrRem01RemValeur", 'sage'),
                "arFrais03FrRem01RemType" => __("arFrais03FrRem01RemType", 'sage'),
                "arFrais03FrRem02RemValeur" => __("arFrais03FrRem02RemValeur", 'sage'),
                "arFrais03FrRem02RemType" => __("arFrais03FrRem02RemType", 'sage'),
                "arFrais03FrRem03RemValeur" => __("arFrais03FrRem03RemValeur", 'sage'),
                "arFrais03FrRem03RemType" => __("arFrais03FrRem03RemType", 'sage'),
                "arCondition" => __("arCondition", 'sage'),
                "arPunet" => __("arPunet", 'sage'),
                "arContremarque" => __("arContremarque", 'sage'),
                "arFactPoids" => __("arFactPoids", 'sage'),
                "arFactForfait" => __("arFactForfait", 'sage'),
                "arSaisieVar" => __("arSaisieVar", 'sage'),
                "arTransfere" => __("arTransfere", 'sage'),
                "arPublie" => __("arPublie", 'sage'),
                "arDateModif" => __("arDateModif", 'sage'),
                "arPhoto" => __("arPhoto", 'sage'),
                "arPrixAchNouv" => __("arPrixAchNouv", 'sage'),
                "arCoefNouv" => __("arCoefNouv", 'sage'),
                "arPrixVenNouv" => __("arPrixVenNouv", 'sage'),
                "arDateApplication" => __("arDateApplication", 'sage'),
                "arCoutStd" => __("arCoutStd", 'sage'),
                "arQteComp" => __("arQteComp", 'sage'),
                "arQteOperatoire" => __("arQteOperatoire", 'sage'),
                "coNo" => __("coNo", 'sage'),
                "cbCoNo" => __("cbCoNo", 'sage'),
                "arPrevision" => __("arPrevision", 'sage'),
                "clNo1" => __("clNo1", 'sage'),
                "cbClNo1" => __("cbClNo1", 'sage'),
                "clNo2" => __("clNo2", 'sage'),
                "cbClNo2" => __("cbClNo2", 'sage'),
                "clNo3" => __("clNo3", 'sage'),
                "cbClNo3" => __("cbClNo3", 'sage'),
                "clNo4" => __("clNo4", 'sage'),
                "cbClNo4" => __("cbClNo4", 'sage'),
                "cbProt" => __("cbProt", 'sage'),
                "cbMarq" => __("cbMarq", 'sage'),
                "cbCreateur" => __("cbCreateur", 'sage'),
                "cbModification" => __("cbModification", 'sage'),
                "cbReplication" => __("cbReplication", 'sage'),
                "cbFlag" => __("cbFlag", 'sage'),
                "site" => __("site", 'sage'),
                "categories" => __("categories", 'sage'),
                "rayons" => __("rayons", 'sage'),
                "constructeur" => __("constructeur", 'sage'),
                "constructeurRef" => __("constructeurRef", 'sage'),
                "crosselling" => __("crosselling", 'sage'),
                "upselling" => __("upselling", 'sage'),
                "vu" => __("vu", 'sage'),
                "vendu" => __("vendu", 'sage'),
                "nomenclature" => __("nomenclature", 'sage'),
                "reviser" => __("reviser", 'sage'),
                "stockManuel" => __("stockManuel", 'sage'),
                "dateInventaire" => __("dateInventaire", 'sage'),
                "surstock" => __("surstock", 'sage'),
                "keywords" => __("keywords", 'sage'),
                "rowguid" => __("rowguid", 'sage'),
                "ip" => __("ip", 'sage'),
                "designation" => __("designation", 'sage'),
                "liens" => __("liens", 'sage'),
                "details" => __("details", 'sage'),
                "logos" => __("logos", 'sage'),
                "arEdiCode" => __("arEdiCode", 'sage'),
                "arType" => __("arType", 'sage'),
                "rpCodeDefaut" => __("rpCodeDefaut", 'sage'),
                "arNature" => __("arNature", 'sage'),
                "arDelaiFabrication" => __("arDelaiFabrication", 'sage'),
                "arNbColis" => __("arNbColis", 'sage'),
                "arDelaiPeremption" => __("arDelaiPeremption", 'sage'),
                "arDelaiSecurite" => __("arDelaiSecurite", 'sage'),
                "arFictif" => __("arFictif", 'sage'),
                "arSousTraitance" => __("arSousTraitance", 'sage'),
                "arTypeLancement" => __("arTypeLancement", 'sage'),
                "arCycle" => __("arCycle", 'sage'),
                "arCriticite" => __("arCriticite", 'sage'),
                "empLibre" => __("empLibre", 'sage'),
                "ficheTechnique" => __("ficheTechnique", 'sage'),
                "rotation" => __("rotation", 'sage'),
                "prixAuKilo" => __("prixAuKilo", 'sage'),
                "d_laiDApprovisionnement" => __("d_laiDApprovisionnement", 'sage'),
                "modeDeSuivi" => __("modeDeSuivi", 'sage'),
                "ordre" => __("ordre", 'sage'),
                "ecotaxe" => __("ecotaxe", 'sage'),
                "sorecop" => __("sorecop", 'sage'),
                "nouProSelRup" => __("nouProSelRup", 'sage'),
                "cbCreation" => __("cbCreation", 'sage'),
                "cbCreationUser" => __("cbCreationUser", 'sage'),
                "arInterdireCommande" => __("arInterdireCommande", 'sage'),
                "arExclure" => __("arExclure", 'sage'),
                "prices" => __("Prices", 'sage'),
                "canEditArSuiviStock" => __("Can Edit ArSuiviStock", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_last_update' => __("Dernière synchronisation dans Wordpress", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_arRef' => __("arRef", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_prices' => __("Prices", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_max_price' => __("Prix max", 'sage'),
                SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_postId' => __("Wordpress ID", 'sage'),
            ],
            'words' => [
                'contains' => __("Contient", 'sage'),
                'endsWith' => __("Se termine par", 'sage'),
                'ncontains' => __("Ne contient pas", 'sage'),
                'nendsWith' => __("Ne se termine pas par", 'sage'),
                'nstartsWith' => __("Ne commence pas par", 'sage'),
                'startsWith' => __("Commence par", 'sage'),
                'gt' => __("Plus grand que", 'sage'),
                'gte' => __("Plus grand ou égal", 'sage'),
                'lt' => __("Moins que", 'sage'),
                'lte' => __("Inférieur ou égal", 'sage'),
                'ngt' => __("Pas plus grand que", 'sage'),
                'ngte' => __("Pas supérieur ou égal", 'sage'),
                'nlt' => __("Pas moins que", 'sage'),
                'nlte' => __("Pas inférieur ou égal", 'sage'),
                'eq' => __("Égal", 'sage'),
                'in' => __("Dedans", 'sage'),
                'neq' => __("Pas égal", 'sage'),
                'nin' => __("Pas dedans", 'sage'),
                'taskJobDoneSpeed' => __("Nombre d'opérations par seconde", 'sage'),
                'remainingTime' => __("Temps restant", 'sage'),
                'fixTheProblem' => __("Résoudre le problème", 'sage'),
            ],
            'sentences' => [
                'multipleDoPieces' => __("Plusieurs documents de ventes correspondent à ce numéro, veuillez spécifier duquel il s'agit", 'sage'),
                'fDoceneteteAlreadyHasOrders' => __("Ce document de vente est déjà lié au(x) commande(s)", 'sage'),
                'synchronizeOrder' => __("Voulez vous vraiment synchroniser la commande Woocommerce avec le document de vente Sage ?", "sage"),
                'desynchronizeOrder' => __("Voulez vous vraiment désynchroniser la commande Woocommerce avec le document de vente Sage ?", "sage"),
                'nbThreads' => __("Nombre de d'opérations simultanées (nb threads)", "sage"),
                'hasErrorWebsocket' => __("Wordpress n'arrive pas à se connecter à l'API pour la raison suivante", "sage"),
            ],
            'enum' => [
                'syncWebsiteState' => [
                    0 => __("[Sage] Création des tâches de synchronisation en cours ...", "sage"), // CreateTasks
                    1 => __("[Sage] Synchronisation en cours ...", "sage"), // DoTasks
                ],
                'taskJobType' => [
                    0 => __('Créer automatiquement le client Sage', 'sage'), // AutoCreateSageFcomptet => auto_create_sage_fcomptet
                    1 => __('Importer automatiquement les anciens clients Woocommerce', 'sage'), // AutoImportSageFcomptet => auto_import_sage_fcomptet
                    2 => __('Créer automatiquement le compte Wordpress', 'sage'), // AutoCreateWebsiteAccount => auto_create_wordpress_account
                    3 => __('Importer automatiquement les anciens clients Sage', 'sage'), // AutoImportWebsiteAccount => auto_import_wordpress_account
                    4 => __('Créer automatiquement le document de vente Sage', 'sage'), // AutoCreateSageFdocentete => auto_create_sage_fdocentete
                    5 => __('Créer automatiquement la commande Woocommerce', 'sage'), // AutoCreateWebsiteOrder => auto_create_wordpress_order
                    6 => __('Créer automatiquement le produit Woocommerce', 'sage'), // AutoCreateWebsiteArticle => auto_create_wordpress_article
                    7 => __('Importer automatiquement les anciens produits Sage', 'sage'), // AutoImportWebsiteArticle => auto_import_wordpress_article
                    8 => __('Importer automatiquement les anciens documents de vente Sage', 'sage'), // AutoImportWebsiteOrder => auto_import_wordpress_order
                ]
            ]
        ];
    }
}
