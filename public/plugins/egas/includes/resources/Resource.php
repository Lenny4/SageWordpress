<?php

declare(strict_types=1);

namespace App\resources;

use App\class\SageEntityMetadata;
use Closure;
use DateTime;
use stdClass;

abstract class Resource
{
    /**
     * Title displayed in left menu and settings
     */
    protected string $title;

    /**
     * Description displayed in the settings
     */
    protected string $description;

    /**
     * Field to request this entity in GraphQL
     */
    protected string $entityName;

    /**
     * Model of this entity in GraphQL
     */
    protected string $typeModel;

    protected string $defaultSortField;

    /**
     * Default fields selected in settings wp-admin/admin.php?page=sage_settings&tab=fDocentetes
     */
    protected array $defaultFields;

    /**
     * Fields that we must request even if they are not selected in the fields to show
     * these fields allow to identify this entity
     */
    protected array $mandatoryFields;

    /**
     * Filter type of this entity in GraphQL
     */
    protected string $filterType;

    protected string $transDomain;

    /**
     * Further options to show besides "Fields to show" and "Default per page"
     */
    protected Closure $options;

    /**
     * Callback which transform data of Sage entity to the metadata
     */
    protected Closure $metadata;

    protected Closure $bddMetadata;

    /**
     * Meta key which give the identifier value
     */
    protected string $metaKeyIdentifier;

    protected string $table;

    protected ?string $postType = null; // used for public function removeUpdateApi
    /**
     * Meta table to use
     */
    protected string $metaTable;

    /**
     * Column in the meta table to use to identify
     */
    protected string $metaColumnIdentifier;

    /**
     * @var ImportConditionDto[]
     */
    protected array $importCondition;

    protected Closure $import;

    protected Closure $selectionSet;

    protected ?Closure $postUrl = null;

    /**
     * Can be use if the Sage entity has multiple column as id
     */
    protected ?Closure $getIdentifier = null;
    protected Closure $sageEntity;
    protected Closure $importFromSage;
    private Closure $canImport;

    protected function __construct()
    {
        $this->canImport = function (stdClass|array|null $entity): array {
            $r = [];
            if (empty($entity)) {
                return $r;
            }

            $entity = (array)$entity;
            foreach ($this->importCondition as $importCondition) {
                $v = $entity[$importCondition->getField()];
                if (is_array($importCondition->getValue())) {
                    if (!in_array($v, $importCondition->getValue())) {
                        $r[] = $importCondition->getMessage()($entity);
                    }
                } elseif ($v !== $importCondition->getValue()) {
                    $r[] = $importCondition->getMessage()($entity);
                }
            }

            return $r;
        };
    }

    public static function getDefaultResourceFilter(): array
    {
        return ['values' => []];
    }

    public static function supports(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Resource
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): Resource
    {
        $this->description = $description;
        return $this;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): Resource
    {
        $this->entityName = $entityName;
        return $this;
    }

    public function getTypeModel(): string
    {
        return $this->typeModel;
    }

    public function setTypeModel(string $typeModel): Resource
    {
        $this->typeModel = $typeModel;
        return $this;
    }

    public function getDefaultSortField(): string
    {
        return $this->defaultSortField;
    }

    public function setDefaultSortField(string $defaultSortField): Resource
    {
        $this->defaultSortField = $defaultSortField;
        return $this;
    }

    public function getDefaultFields(): array
    {
        return $this->defaultFields;
    }

    public function setDefaultFields(array $defaultFields): Resource
    {
        $this->defaultFields = $defaultFields;
        return $this;
    }

    public function getMandatoryFields(): array
    {
        return $this->mandatoryFields;
    }

    public function setMandatoryFields(array $mandatoryFields): Resource
    {
        $this->mandatoryFields = $mandatoryFields;
        return $this;
    }

    public function getFilterType(): string
    {
        return $this->filterType;
    }

    public function setFilterType(string $filterType): Resource
    {
        $this->filterType = $filterType;
        return $this;
    }

    public function getTransDomain(): string
    {
        return $this->transDomain;
    }

    public function setTransDomain(string $transDomain): Resource
    {
        $this->transDomain = $transDomain;
        return $this;
    }

    public function getOptions(): Closure
    {
        return $this->options;
    }

    public function setOptions(Closure $options): Resource
    {
        $this->options = $options;
        return $this;
    }

    public function getMetadata(): Closure
    {
        return $this->metadata;
    }

    public function setMetadata(Closure $metadata): Resource
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetaKeyIdentifier(): string
    {
        return $this->metaKeyIdentifier;
    }

    public function setMetaKeyIdentifier(string $metaKeyIdentifier): Resource
    {
        $this->metaKeyIdentifier = $metaKeyIdentifier;
        return $this;
    }

    public function getMetaTable(): string
    {
        return $this->metaTable;
    }

    public function setMetaTable(string $metaTable): Resource
    {
        $this->metaTable = $metaTable;
        return $this;
    }

    public function getMetaColumnIdentifier(): string
    {
        return $this->metaColumnIdentifier;
    }

    public function setMetaColumnIdentifier(string $metaColumnIdentifier): Resource
    {
        $this->metaColumnIdentifier = $metaColumnIdentifier;
        return $this;
    }

    public function getCanImport(): Closure
    {
        return $this->canImport;
    }

    public function setCanImport(Closure $canImport): Resource
    {
        $this->canImport = $canImport;
        return $this;
    }

    public function getImport(): Closure
    {
        return $this->import;
    }

    public function setImport(Closure $import): Resource
    {
        $this->import = $import;
        return $this;
    }

    public function getPostUrl(): ?Closure
    {
        return $this->postUrl;
    }

    public function setPostUrl(?Closure $postUrl): Resource
    {
        $this->postUrl = $postUrl;
        return $this;
    }

    public function getGetIdentifier(): ?Closure
    {
        return $this->getIdentifier;
    }

    public function setGetIdentifier(?Closure $getIdentifier): Resource
    {
        $this->getIdentifier = $getIdentifier;
        return $this;
    }

    public function getImportCondition(): array
    {
        return $this->importCondition;
    }

    public function setImportCondition(array $importCondition): Resource
    {
        $this->importCondition = $importCondition;
        return $this;
    }

    public function getMandatoryMetadata(): array
    {
        return [
            new SageEntityMetadata(field: '_updateApi'),
            new SageEntityMetadata(field: '_postId', showInOptions: true),
            new SageEntityMetadata(field: '_last_update', value: static fn(StdClass $stdClass): string => (new DateTime())->format('Y-m-d H:i:s'), showInOptions: true),
        ];
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function getPostType(): ?string
    {
        return $this->postType;
    }

    public function setPostType(?string $postType): self
    {
        $this->postType = $postType;
        return $this;
    }

    public function getBddMetadata(): Closure
    {
        return $this->bddMetadata;
    }

    public function setBddMetadata(Closure $bddMetadata): self
    {
        $this->bddMetadata = $bddMetadata;
        return $this;
    }

    public function getSageEntity(): Closure
    {
        return $this->sageEntity;
    }

    public function setSageEntity(Closure $sageEntity): self
    {
        $this->sageEntity = $sageEntity;
        return $this;
    }

    public function getImportFromSage(): Closure
    {
        return $this->importFromSage;
    }

    public function setImportFromSage(Closure $importFromSage): self
    {
        $this->importFromSage = $importFromSage;
        return $this;
    }

    public function getSelectionSet(): Closure
    {
        return $this->selectionSet;
    }

    public function setSelectionSet(Closure $selectionSet): self
    {
        $this->selectionSet = $selectionSet;
        return $this;
    }
}
