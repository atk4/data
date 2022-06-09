<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

trait FieldPropertiesTrait
{
    /** @var string|null Field type. Name of type registered in \Doctrine\DBAL\Types\Type. */
    public $type;

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
     * If value of this field is defined by a model, this property
     * will contain reference link.
     *
     * @var string|null
     */
    protected $referenceLink;

    /** @var string|null Actual field name. */
    public $actual;

    /**
     * Is it system field?
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /** @var mixed Default value of field. */
    public $default;

    /**
     * Setting this to true will never actually load or store
     * the field in the database. It will action as normal,
     * but will be skipped by load/iterate/update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_save = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $read_only = false;

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

    /**
     * Mandatory field must not be null. The value must be set, even if
     * it's an empty value.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Required field must have non-empty value. A null value is considered empty too.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $required = false;
}
