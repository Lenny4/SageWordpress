<?php

namespace App\class;

use App\Sage;
use App\SageSettings;

final class SageEntityMenu
{
    public const FCOMPTET_ENTITY_NAME = 'fComptets';

    public const FCOMPTET_TYPE_MODEL = 'FComptet';

    public const FCOMPTET_DEFAULT_SORT = 'ctNum';

    public const FCOMPTET_FILTER_TYPE = 'FComptetFilterInput';

    public const FCOMPTET_DEFAULT_FIELDS = [
        'ctNum',
        'ctIntitule',
        'ctContact',
        'ctEmail',
        SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_last_update',
    ];

    public const FDOCENTETE_ENTITY_NAME = 'fDocentetes';

    public const FDOCENTETE_TYPE_MODEL = 'FDocentete';

    public const FDOCENTETE_DEFAULT_SORT = 'doPiece';

    public const FDOCENTETE_FILTER_TYPE = 'FDocenteteFilterInput';

    public const FDOCENTETE_DEFAULT_FIELDS = [
        'doPiece',
        'doType',
        'doDate',
    ];

    public const FARTICLE_ENTITY_NAME = 'fArticles';

    public const FARTICLE_TYPE_MODEL = 'FArticle';

    public const FARTICLE_DEFAULT_SORT = 'arRef';

    public const FARTICLE_FILTER_TYPE = 'FArticleFilterInput';

    public const FARTICLE_DEFAULT_FIELDS = [
        'arRef',
        'arDesign',
        SageSettings::PREFIX_META_DATA . '_' . Sage::TOKEN . '_last_update',
    ];

    public const PCATTARIF_ENTITY_NAME = 'pCattarifs';

    public const PCATTARIF_TYPE_MODEL = 'PCattarif';

    /**
     * @param string[] $mandatoryFields
     * @param string[] $defaultFields
     * @param SageEntityMetadata[] $metadata
     */
    public function __construct(
        private string $title,
        private string $description,
        private string $entityName,
        private string $typeModel,
        private string $defaultSortField,
        private array  $defaultFields,
        private array  $mandatoryFields,
        private string $filterType,
        private string $transDomain,
        private array  $options,
        private array  $actions,
        private array  $metadata,
        private string $metaKeyIdentifier,
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
}
