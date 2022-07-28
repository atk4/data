<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

trait FieldPropertiesTrait
{
    /** @var string|null Persistence field name, if null, shortName is used. */
    public ?string $actual = null;
    /** @var bool Persistence option, if true, field is not loaded nor inserted/updated. */
    public bool $neverPersist = false;
    /** @var bool Persistence option, if true, field is not inserted/updated. */
    public bool $neverSave = false;

    /** @var string|null DBAL type registered in \Doctrine\DBAL\Types\Type. */
    public ?string $type = null;
    /** @var bool Mandatory field must not be null. The value must be set, even if it's an empty value. */
    public bool $mandatory = false;
    /** @var bool Required field must have non-empty value. A null value is considered empty too. */
    public bool $required = false;

    /** @var array|null For several types enum can provide list of available options. ['blue', 'red']. */
    public $enum;

    /**
     * For fields that can be selected, values can represent interpretation of the values,
     * for instance ['F' => 'Female', 'M' => 'Male'].
     *
     * @var array|null
     */
    public $values;

    /**
     * If value of this field is defined by a model, this property will contain reference link.
     */
    protected ?string $referenceLink = null;

    /** @var bool Is it system field? System fields are be always loaded and saved. */
    public bool $system = false;

    /** @var mixed Default value of field. */
    public $default;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     */
    public bool $readOnly = false;

    /**
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string|null
     */
    public $caption;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [];
}
