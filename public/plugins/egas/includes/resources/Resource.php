<?php

namespace App\resources;

use Closure;

abstract class Resource
{
    /**
     * Title displayed in left menu and settings
     */
    public string $title;
    /**
     * Description displayed in the settings
     */
    public string $description;
    /**
     * Field to request this entity in GraphQL
     */
    public string $entityName;
    /**
     * Model of this entity in GraphQL
     */
    public string $typeModel;
    public string $defaultSortField;
    /**
     * Default fields selected in settings wp-admin/admin.php?page=sage_settings&tab=fDocentetes
     */
    public array $defaultFields;
    /**
     * Fields that we must request even if they are not selected in the fields to show
     * these fields allow to identify this entity
     */
    public array $mandatoryFields;
    /**
     * Filter type of this entity in GraphQL
     */
    public string $filterType;
    public string $transDomain;
    /**
     * Further options to show besides "Fields to show" and "Default per page"
     */
    public array $options;
    /**
     * Callback which transform data of Sage entity to the metadata
     */
    public Closure $metadata;
    /**
     * Meta key which give the identifier value
     */
    public string $metaKeyIdentifier;
    /**
     * Meta table to use
     */
    public string $metaTable;
    /**
     * Column in the meta table to use to identify
     */
    public string $metaColumnIdentifier;
    public Closure $canImport;
    public Closure $import;
    public array $selectionSet;
    public ?Closure $postUrl = null;
    /**
     * Can be use if the Sage entity has multiple column as id
     */
    public ?Closure $getIdentifier = null;
}
