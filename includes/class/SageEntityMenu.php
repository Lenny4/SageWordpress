<?php

namespace App\class;

class SageEntityMenu
{
    public const FCOMPTET_ENTITY_NAME = 'fComptets';
    public const FDOCENTETE_ENTITY_NAME = 'fDocentetes';

    private string $title;
    private string $entityName;
    /**
     * @var string[]
     */
    private array $mandatoryFields;
    private string $filterType;
    private string $transDomain;

    /**
     * @param string[] $mandatoryFields
     */
    public function __construct(string $title, string $entityName, array $mandatoryFields, string $filterType, string $transDomain)
    {
        $this->title = $title;
        $this->entityName = $entityName;
        $this->mandatoryFields = $mandatoryFields;
        $this->filterType = $filterType;
        $this->transDomain = $transDomain;
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
}
