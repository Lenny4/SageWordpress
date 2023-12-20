<?php

namespace App\class;

class SageEntityMenu
{
    public const FCOMPTET_ENTITY_NAME = 'fComptets';
    public const DEFAULT_FCOMPTET_FIELDS = [
        'ctNum',
        'ctIntitule',
        'ctContact',
        'ctEmail',
    ];

    public const FDOCENTETE_ENTITY_NAME = 'fDocentetes';
    public const DEFAULT_FDOCENTETE_FIELDS = [
        'doPiece',
        'doType',
        'doDate',
    ];

    /**
     * @param string[] $mandatoryFields
     * @param string[] $defaultFields
     */
    public function __construct(
        private string $title,
        private string $entityName,
        private array $defaultFields,
        private array $mandatoryFields,
        private string $filterType,
        private string $transDomain,
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
}
