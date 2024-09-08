<?php

namespace App\class;

use Closure;

final class SageEntityMenu
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

    /**
     * @param string[] $mandatoryFields
     * @param string[] $defaultFields
     * @param SageEntityMetadata[] $metadata
     */
    public function __construct(
        /**
         * Title displayed in left menu and settings
         */
        private string   $title,
        /**
         * Description displayed in the settings
         */
        private string   $description,
        /**
         * Field to request this entity in GraphQL
         */
        private string   $entityName,
        /**
         * Model of this entity in GraphQL
         */
        private string   $typeModel,
        private string   $defaultSortField,
        /**
         * Default fields selected in settings wp-admin/admin.php?page=sage_settings&tab=fDocentetes
         */
        private array    $defaultFields,
        /**
         * Fields that we must request even if they are not selected in the fields to show
         * these fields allow to identify this entity
         */
        private array    $mandatoryFields,
        /**
         * Filter type of this entity in GraphQL
         */
        private string   $filterType,
        private string   $transDomain,
        /**
         * Further options to show besides "Fields to show" and "Default per page"
         */
        private array    $options,
        /**
         * Callback which can be trigger by button in the list view templates/sage/fDocentetes/list_action.html.twig
         */
        private array    $actions,
        /**
         * Callback which transform data of Sage entity to the metadata
         */
        private array    $metadata,
        /**
         * Meta key which give the identifier value
         */
        private string   $metaKeyIdentifier,
        /**
         * Meta table to use
         */
        private string   $metaTable,
        /**
         * Column in the meta table to use to identify
         */
        private string   $metaColumnIdentifier,
        /**
         * Can be use if the Sage entity has multiple column as id
         */
        private ?Closure $getIdentifier = null,
    )
    {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = $entityName;
        return $this;
    }

    public function getMandatoryFields(): array
    {
        return $this->mandatoryFields;
    }

    public function setMandatoryFields(array $mandatoryFields): self
    {
        $this->mandatoryFields = $mandatoryFields;
        return $this;
    }

    public function getFilterType(): string
    {
        return $this->filterType;
    }

    public function setFilterType(string $filterType): self
    {
        $this->filterType = $filterType;
        return $this;
    }

    public function getTransDomain(): string
    {
        return $this->transDomain;
    }

    public function setTransDomain(string $transDomain): self
    {
        $this->transDomain = $transDomain;
        return $this;
    }

    public function getDefaultFields(): array
    {
        return $this->defaultFields;
    }

    public function setDefaultFields(array $defaultFields): self
    {
        $this->defaultFields = $defaultFields;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getTypeModel(): string
    {
        return $this->typeModel;
    }

    public function setTypeModel(string $typeModel): self
    {
        $this->typeModel = $typeModel;
        return $this;
    }

    public function getDefaultSortField(): string
    {
        return $this->defaultSortField;
    }

    public function setDefaultSortField(string $defaultSortField): self
    {
        $this->defaultSortField = $defaultSortField;
        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function setActions(array $actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetaKeyIdentifier(): string
    {
        return $this->metaKeyIdentifier;
    }

    public function setMetaKeyIdentifier(string $metaKeyIdentifier): self
    {
        $this->metaKeyIdentifier = $metaKeyIdentifier;
        return $this;
    }

    public function getGetIdentifier(): ?Closure
    {
        return $this->getIdentifier;
    }

    public function setGetIdentifier(?Closure $getIdentifier): self
    {
        $this->getIdentifier = $getIdentifier;
        return $this;
    }

    public function getMetaTable(): string
    {
        return $this->metaTable;
    }

    public function setMetaTable(string $metaTable): self
    {
        $this->metaTable = $metaTable;
        return $this;
    }

    public function getMetaColumnIdentifier(): string
    {
        return $this->metaColumnIdentifier;
    }

    public function setMetaColumnIdentifier(string $metaColumnIdentifier): self
    {
        $this->metaColumnIdentifier = $metaColumnIdentifier;
        return $this;
    }
}
