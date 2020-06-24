<?php

declare(strict_types=1);

namespace atk4\data;

use atk4\core\AppScopeTrait;
use atk4\core\CollectionTrait;
use atk4\core\ContainerTrait;
use atk4\core\DIContainerTrait;
use atk4\core\DynamicMethodTrait;
use atk4\core\FactoryTrait;
use atk4\core\HookTrait;
use atk4\core\InitializerTrait;
use atk4\core\ReadableCaptionTrait;
use atk4\dsql\Query;

/**
 * Data model class.
 *
 * @property Field[]|Reference[] $elements
 */
class Model implements \IteratorAggregate
{
    use ContainerTrait {
        add as _add;
    }
    use DynamicMethodTrait;
    use HookTrait;
    use InitializerTrait {
        init as _init;
    }
    use DIContainerTrait;
    use FactoryTrait;
    use AppScopeTrait;
    use CollectionTrait;
    use ReadableCaptionTrait;

    /** @const string */
    public const HOOK_BEFORE_LOAD = self::class . '@beforeLoad';
    /** @const string */
    public const HOOK_AFTER_LOAD = self::class . '@afterLoad';
    /** @const string */
    public const HOOK_BEFORE_UNLOAD = self::class . '@beforeUnload';
    /** @const string */
    public const HOOK_AFTER_UNLOAD = self::class . '@afterUnload';

    /** @const string */
    public const HOOK_BEFORE_INSERT = self::class . '@beforeInsert';
    /** @const string */
    public const HOOK_AFTER_INSERT = self::class . '@afterInsert';
    /** @const string */
    public const HOOK_BEFORE_UPDATE = self::class . '@beforeUpdate';
    /** @const string */
    public const HOOK_AFTER_UPDATE = self::class . '@afterUpdate';
    /** @const string */
    public const HOOK_BEFORE_DELETE = self::class . '@beforeDelete';
    /** @const string */
    public const HOOK_AFTER_DELETE = self::class . '@afterDelete';

    /** @const string */
    public const HOOK_BEFORE_SAVE = self::class . '@beforeSave';
    /** @const string */
    public const HOOK_AFTER_SAVE = self::class . '@afterSave';

    /** @const string Executed when execution of self::atomic() failed. */
    public const HOOK_ROLLBACK = self::class . '@rollback';

    /** @const string Executed for every field set using self::set() method. */
    public const HOOK_NORMALIZE = self::class . '@normalize';
    /** @const string Executed when self::validate() method is called. */
    public const HOOK_VALIDATE = self::class . '@validate';
    /** @const string Executed when self::onlyFields() method is called. */
    public const HOOK_ONLY_FIELDS = self::class . '@onlyFields';

    // {{{ Properties of the class

    /**
     * The class used by addField() method.
     *
     * @todo use Field::class here and refactor addField() method to not use namespace prefixes.
     *       but because that's backward incompatible change, then we can do that only in next
     *       major version.
     *
     * @var string|array
     */
    public $_default_seed_addField = [Field::class];

    /**
     * The class used by addField() method.
     *
     * @var string|array
     */
    public $_default_seed_addExpression = [Field\Callback::class];

    /**
     * The class used by addRef() method.
     *
     * @var string|array
     */
    public $_default_seed_addRef = [Reference::class];

    /**
     * The class used by hasOne() method.
     *
     * @var string|array
     */
    public $_default_seed_hasOne = [Reference\HasOne::class];

    /**
     * The class used by hasMany() method.
     *
     * @var string|array
     */
    public $_default_seed_hasMany = [Reference\HasMany::class];

    /**
     * The class used by containsOne() method.
     *
     * @var string|array
     */
    public $_default_seed_containsOne = [Reference\ContainsOne::class];

    /**
     * The class used by containsMany() method.
     *
     * @var string
     */
    public $_default_seed_containsMany = [Reference\ContainsMany::class];

    /**
     * The class used by join() method.
     *
     * @var string|array
     */
    public $_default_seed_join = [Join::class];

    /**
     * Default class for addAction().
     *
     * @var string|array
     */
    public $_default_seed_action = [UserAction\Generic::class];

    /**
     * @var array Collection containing Field Objects - using key as the field system name
     */
    protected $fields = [];

    /**
     * @var array Collection of actions - using key as action system name
     */
    protected $actions = [];

    /**
     * Contains name of table, session key, collection or file where this
     * model normally lives. The interpretation of the table will be decoded
     * by persistence driver.
     *
     * You can define this field as associative array where "key" is used
     * as the name of persistence driver. Here is example for mysql and default:
     *
     * $table = ['user', 'mysql'=>'tbl_user'];
     *
     * @var string|array
     */
    public $table;

    /**
     * Use alias for $table.
     *
     * @var string
     */
    public $table_alias;

    /**
     * Sequence name. Some DB engines use sequence for generating auto_increment IDs.
     *
     * @var string
     */
    public $sequence;

    /**
     * Persistence driver inherited from atk4\data\Persistence.
     *
     * @var Persistence
     */
    public $persistence;

    /**
     * Persistence store some custom information in here that may be useful
     * for them. The key is the name of persistence driver.
     *
     * @var array
     */
    public $persistence_data = [];

    /**
     * Conditions list several conditions that must be met by all the
     * records in the associated DataSet. Conditions are stored as
     * elements of array of 1 to 3. Use addCondition() to add new
     * conditions.
     *
     * @var array
     */
    public $conditions = [];

    /**
     * Array of limit set.
     *
     * @var array
     */
    public $limit = [];

    /**
     * Array of set order by.
     *
     * @var array
     */
    public $order = [];

    /**
     * Array of WITH cursors set.
     *
     * @var array
     */
    public $with = [];

    /**
     * Currently loaded record data. This record is associative array
     * that contain field=>data pairs. It may contain data for un-defined
     * fields only if $onlyFields mode is false.
     *
     * Avoid accessing $data directly, use set() / get() instead.
     *
     * @var array
     */
    public $data = [];

    /**
     * After loading an active record from DataSet it will be stored in
     * $data property and you can access it using get(). If you use
     * set() to change any of the data, the original value will be copied
     * here.
     *
     * If the value you set equal to the original value, then the key
     * in this array will be removed.
     *
     * The $dirty data will be reset after you save() the data but it is
     * still available to all before/after save handlers.
     *
     * @var array
     */
    public $dirty = [];

    /**
     * Setting model as read_only will protect you from accidentally
     * updating the model. This property is intended for UI and other code
     * detecting read-only models and acting accordingly.
     *
     * SECURITY WARNING: If you are looking for a RELIABLE way to restrict access
     * to model data, please check Secure Enclave extension.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Contains ID of the current record. If the value is null then the record
     * is considered to be new.
     *
     * @var mixed
     */
    public $id;

    /**
     * While in most cases your id field will be called 'id', sometimes
     * you would want to use a different one or maybe don't create field
     * at all.
     *
     * @var string
     */
    public $id_field = 'id';

    /**
     * Title field is used typically by UI components for a simple human
     * readable row title/description.
     *
     * @var string
     */
    public $title_field = 'name';

    /**
     * Caption of the model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string
     */
    public $caption;

    /**
     * When using onlyFields() this property will contain list of desired
     * fields.
     *
     * If you set onlyFields() before loading the data for this model, then
     * only that set of fields will be available. Attempt to access any other
     * field will result in exception. This is to ensure that you do not
     * accidentally access field that you have explicitly excluded.
     *
     * The default behavior is to return NULL and allow you to set new
     * fields even if addField() was not used to set the field.
     *
     * onlyFields() always allows to access fields with system = true.
     *
     * @var false|array
     */
    public $only_fields = false;

    /**
     * When set to true, all the field types will be enforced and
     * normalized when setting.
     *
     * @var bool
     */
    public $strict_types = true;

    /**
     * When set to true, loading model from database will also
     * perform value normalization. Use this if you think that
     * persistence may contain badly formatted data that may
     * impact your business logic.
     *
     * @var bool
     */
    public $load_normalization = false;

    /**
     * Models that contain expressions will automatically reload after save.
     * This is to ensure that any SQL-based calculation are executed and
     * updated correctly after you have performed any modifications to
     * the fields.
     *
     * You can set this property to "true" or "false" if you want to explicitly
     * enable or disable reloading.
     *
     * @var bool|null
     */
    public $reload_after_save;

    /**
     * If this model is "contained into" another model by using containsOne
     * or containsMany reference, then this property will contain reference
     * to top most parent model.
     *
     * @var Model|null
     */
    public $contained_in_root_model;

    // }}}

    // {{{ Basic Functionality, field definition, set() and get()

    /**
     * Creation of the new model can be done in two ways:.
     *
     * $m = $db->add(new Model());
     *
     * or
     *
     * $m = new Model($db);
     *
     * The second use actually calls add() but is preferred usage because:
     *  - it's shorter
     *  - type hinting will work;
     *  - you can specify string for a table
     *
     * @param Persistence|array $persistence
     * @param string|array      $defaults
     */
    public function __construct($persistence = null, $defaults = [])
    {
        if (is_string($persistence) || is_array($persistence)) {
            $defaults = $persistence;
            $persistence = null;
        }

        if (is_string($defaults) || $defaults === false) {
            $defaults = ['table' => $defaults];
        }

        if (isset($defaults[0])) {
            $defaults['table'] = $defaults[0];
            unset($defaults[0]);
        }

        $this->setDefaults($defaults);

        if ($persistence) {
            $persistence->add($this);
        }
    }

    /**
     * Clones model object.
     */
    public function __clone()
    {
        $this->_cloneCollection('elements');
        $this->_cloneCollection('fields');
        $this->_cloneCollection('actions');
    }

    /**
     * Extend this method to define fields of your choice.
     */
    public function init(): void
    {
        $this->_init();

        if ($this->id_field) {
            $this->addField($this->id_field, ['system' => true]);
        } else {
            return; // don't declare actions for model without id_field
        }

        if ($this->read_only) {
            return; // don't declare action for read-only model
        }

        // Declare our basic CRUD actions for the model.
        $this->addAction('add', [
            'fields' => true,
            'modifier' => UserAction\Generic::MODIFIER_CREATE,
            'scope' => UserAction\Generic::NO_RECORDS,
            'callback' => 'save',
            'description' => 'Add ' . $this->getModelCaption(),
            'ui' => ['icon' => 'plus'],
        ]);
        $this->addAction('edit', [
            'fields' => true,
            'modifier' => UserAction\Generic::MODIFIER_UPDATE,
            'scope' => UserAction\Generic::SINGLE_RECORD,
            'callback' => 'save',
            'ui' => ['icon' => 'edit', 'button' => [null, 'icon' => 'edit'], 'execButton' => [\atk4\ui\Button::class, 'Save', 'blue']],
        ]);
        $this->addAction('delete', [
            'scope' => UserAction\Generic::SINGLE_RECORD,
            'modifier' => UserAction\Generic::MODIFIER_DELETE,
            'ui' => ['icon' => 'trash', 'button' => [null, 'icon' => 'red trash'], 'confirm' => 'Are you sure?'],
            'callback' => function ($model) {
                return $model->delete();
            },
        ]);

        $this->addAction('validate', [
            //'scope'=> any!
            'description' => 'Provided with modified values will validate them but will not save',
            'modifier' => UserAction\Generic::MODIFIER_READ,
            'fields' => true,
            'system' => true, // don't show by default
            'args' => ['intent' => 'string'],
        ]);
    }

    /**
     * Perform validation on a currently loaded values, must return Array in format:
     *  ['field'=>'must be 4 digits exactly'] or empty array if no errors were present.
     *
     * You may also use format:
     *  ['field'=>['must not have character [ch]', 'ch'=>$bad_character']] for better localization of error message.
     *
     * Always use
     *   return array_merge(parent::validate($intent), $errors);
     *
     * @param string $intent by default only 'save' is used (from beforeSave) but you can use other intents yourself
     *
     * @return array [field => err_spec]
     */
    public function validate($intent = null)
    {
        $errors = [];
        foreach ($this->hook(self::HOOK_VALIDATE, [$intent]) as $handler_error) {
            if ($handler_error) {
                $errors = array_merge($errors, $handler_error);
            }
        }

        return $errors;
    }

    /**
     * TEMPORARY to spot any use of $model->add(new Field(), ['bleh']); form.
     */
    public function add(object $obj, array $defaults = []): object
    {
        if ($obj instanceof Field) {
            throw new Exception('You should always use addField() for adding fields, not add()');
        }

        return $this->_add($obj, $defaults);
    }

    /**
     * Adds new field into model.
     *
     * @param string       $name
     * @param array|object $seed
     *
     * @return Field
     */
    public function addField($name, $seed = [])
    {
        if (is_object($seed)) {
            $field = $seed;
        } else {
            $field = $this->fieldFactory($seed);
        }

        return $this->_addIntoCollection($name, $field, 'fields');
    }

    /**
     * Given a field seed, return a field object.
     *
     * @param array $seed
     *
     * @return Field
     */
    public function fieldFactory($seed = [])
    {
        $seed = $this->mergeSeeds(
            $seed,
            isset($seed['type']) ? ($this->typeToFieldSeed[$seed['type']] ?? null) : null,
            $this->_default_seed_addField
        );

        /** @var Field $field */
        $field = $this->factory($seed);

        return $field;
    }

    protected $typeToFieldSeed = [
        'boolean' => [Field\Boolean::class],
    ];

    /**
     * Adds multiple fields into model.
     *
     * @param array $defaults
     *
     * @return $this
     */
    public function addFields(array $fields, $defaults = [])
    {
        foreach ($fields as $key => $field) {
            if (!is_int($key)) {
                // field name can be passed as array key
                $name = $key;
            } elseif (is_string($field)) {
                // or it can be simple string = field name
                $name = $field;
                $field = [];
            } elseif (is_array($field) && isset($field[0]) && is_string($field[0])) {
                // or field name can be passed as first element of seed array (old behaviour)
                $name = array_shift($field);
            } else {
                // some unsupported format, maybe throw exception here?
                continue;
            }

            $seed = array_merge($defaults, (array) $field);

            $this->addField($name, $seed);
        }

        return $this;
    }

    /**
     * Remove field that was added previously.
     *
     * @return $this
     */
    public function removeField(string $name)
    {
        $this->getField($name); // better exception if field does not exist

        $this->_removeFromCollection($name, 'fields');

        return $this;
    }

    public function hasField(string $name): bool
    {
        return $this->_hasInCollection($name, 'fields');
    }

    public function getField(string $name): Field
    {
        try {
            return $this->_getFromCollection($name, 'fields');
        } catch (\atk4\core\Exception $e) {
            throw (new Exception('Field is not defined in model', 0, $e))
                ->addMoreInfo('model', $this)
                ->addMoreInfo('field', $name);
        }
    }

    /**
     * Sets which fields we will select.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function onlyFields($fields = [])
    {
        $this->hook(self::HOOK_ONLY_FIELDS, [&$fields]);
        $this->only_fields = $fields;

        return $this;
    }

    /**
     * Sets that we should select all available fields.
     *
     * @return $this
     */
    public function allFields()
    {
        $this->only_fields = false;

        return $this;
    }

    private function checkOnlyFieldsField(string $field)
    {
        $this->getField($field); // test if field exists

        if ($this->only_fields) {
            if (!in_array($field, $this->only_fields, true) && !$this->getField($field)->system) {
                throw (new Exception('Attempt to use field outside of those set by onlyFields'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('only_fields', $this->only_fields);
            }
        }
    }

    /**
     * Will return true if any of the specified fields are dirty.
     *
     * @param string|array $field
     *
     * @return bool
     */
    public function isDirty($fields = [])
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            $this->checkOnlyFieldsField($field);

            if (array_key_exists($field, $this->dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|array|null $filter
     *
     * @return array
     */
    public function getFields($filter = null)
    {
        if ($filter === null) {
            return $this->fields;
        } elseif (is_string($filter)) {
            $filter = [$filter];
        }

        return array_filter($this->fields, function (Field $field, $name) use ($filter) {
            // do not return fields outside of "only_fields" scope
            if ($this->only_fields && !in_array($name, $this->only_fields, true)) {
                return false;
            }
            foreach ($filter as $f) {
                if (
                    ($f === 'system' && $field->system)
                    || ($f === 'not system' && !$field->system)
                    || ($f === 'editable' && $field->isEditable())
                    || ($f === 'visible' && $field->isVisible())
                ) {
                    return true;
                } elseif (!in_array($f, ['system', 'not system', 'editable', 'visible'], true)) {
                    throw (new Exception('Filter is not supported'))
                        ->addMoreInfo('filter', $f);
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Set field value.
     *
     * @param string|array $field
     * @param mixed        $value
     *
     * @return $this
     */
    public function set($field, $value = null)
    {
        if (func_num_args() === 1) {
            foreach ($field as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        $this->checkOnlyFieldsField($field);

        $f = $this->getField($field);

        try {
            if ($this->hook(self::HOOK_NORMALIZE, [$f, $value]) !== false) {
                $value = $f->normalize($value);
            }
        } catch (Exception $e) {
            $e->addMoreInfo('field', $field);
            $e->addMoreInfo('value', $value);
            $e->addMoreInfo('f', $f);

            throw $e;
        }

        $original_value = array_key_exists($field, $this->dirty) ? $this->dirty[$field] : $f->default;

        $current_value = array_key_exists($field, $this->data) ? $this->data[$field] : $original_value;

        if (gettype($value) == gettype($current_value) && $value == $current_value) {
            // do nothing, value unchanged
            return $this;
        }

        // perform bunch of standard validation here. This can be re-factored in the future.
        if ($f->read_only) {
            throw (new Exception('Attempting to change read-only field'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('model', $this);
        }

        // enum property support
        if (isset($f->enum) && $f->enum && $f->type !== 'boolean') {
            if ($value === '') {
                $value = null;
            }
            if ($value !== null && !in_array($value, $f->enum, true)) {
                throw (new Exception('This is not one of the allowed values for the field'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $this)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('enum', $f->enum);
            }
        }

        // values property support
        if ($f->values) {
            if ($value === '') {
                $value = null;
            } elseif ($value === null) {
                // all is good
            } elseif (!is_string($value) && !is_int($value)) {
                throw (new Exception('Field can be only one of pre-defined value, so only "string" and "int" keys are supported'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $this)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('values', $f->values);
            } elseif (!array_key_exists($value, $f->values)) {
                throw (new Exception('This is not one of the allowed values for the field'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $this)
                    ->addMoreInfo('value', $value)
                    ->addMoreInfo('values', $f->values);
            }
        }

        if (array_key_exists($field, $this->dirty) && (
            gettype($this->dirty[$field]) == gettype($value) && $this->dirty[$field] == $value
        )) {
            unset($this->dirty[$field]);
        } elseif (!array_key_exists($field, $this->dirty)) {
            $this->dirty[$field] = array_key_exists($field, $this->data) ? $this->data[$field] : $f->default;
        }
        $this->data[$field] = $value;

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     *
     * @param string|array|Model $field
     *
     * @return $this
     */
    public function setNull($field)
    {
        // set temporary hook to disable any normalization (null validation)
        $hookIndex = $this->onHook(self::HOOK_NORMALIZE, function () {
            throw new \atk4\core\HookBreaker(false);
        }, [], PHP_INT_MIN);

        try {
            $this->set($field, null);
        } finally {
            $this->removeHook(self::HOOK_NORMALIZE, $hookIndex, true);
        }

        return $this;
    }

    /**
     * Returns field value.
     * If no field is passed, then returns array of all field values.
     *
     * @param mixed $field
     *
     * @return mixed
     */
    public function get($field = null)
    {
        if ($field === null) {
            // Collect list of eligible fields
            $data = [];
            if ($this->only_fields) {
                // collect data for actual fields
                foreach ($this->only_fields as $field) {
                    $data[$field] = $this->get($field);
                }
            } else {
                // get all fields
                foreach ($this->getFields() as $field => $f) {
                    $data[$field] = $this->get($field);
                }
            }

            return $data;
        }

        $this->checkOnlyFieldsField($field);

        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        return $this->getField($field)->default;
    }

    /**
     * Return (possibly localized) $model->caption.
     * If caption is not set, then generate it from model class name.
     *
     * @return string
     */
    public function getModelCaption()
    {
        return $this->caption ?: $this->readableCaption(
            strpos(static::class, 'class@anonymous') === 0 ? get_parent_class(static::class) : static::class
        );
    }

    /**
     * Return value of $model->get($model->title_field). If not set, returns id value.
     *
     * @return mixed
     */
    public function getTitle()
    {
        if (!$this->title_field) {
            return $this->id;
        }

        return $this->hasField($this->title_field) ? $this->getField($this->title_field)->get() : $this->id;
    }

    /**
     * Returns array of model record titles [id => title].
     *
     * @return array
     */
    public function getTitles()
    {
        $field = $this->title_field && $this->hasField($this->title_field) ? $this->title_field : $this->id_field;

        return array_map(function ($row) use ($field) {
            return $row[$field];
        }, $this->export([$field], $this->id_field));
    }

    /**
     * You can compare new value of the field with existing one without
     * retrieving. In the trivial case it's same as ($value == $model->get($name))
     * but this method can be used for:.
     *
     *  - comparing values that can't be received - passwords, encrypted data
     *  - comparing images
     *  - if get() is expensive (e.g. retrieve object)
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return bool true if $value matches saved one
     */
    public function compare($name, $value)
    {
        return $this->getField($name)->compare($value);
    }

    /**
     * Does field exist?
     */
    public function _isset(string $name): bool
    {
        $this->checkOnlyFieldsField($name);

        return array_key_exists($name, $this->dirty);
    }

    /**
     * Remove current field value and use default.
     *
     * @param string|array $name
     *
     * @return $this
     */
    public function _unset($name)
    {
        $this->checkOnlyFieldsField($name);

        if (array_key_exists($name, $this->dirty)) {
            $this->data[$name] = $this->dirty[$name];
            unset($this->dirty[$name]);
        }

        return $this;
    }

    // }}}

    // {{{ UserAction support

    /**
     * Register new user action for this model. By default UI will allow users to trigger actions
     * from UI.
     *
     * @param string         $name     Action name
     * @param array|callable $defaults
     */
    public function addAction($name, $defaults = []): UserAction\Generic
    {
        if (is_callable($defaults)) {
            $defaults = ['callback' => $defaults];
        }

        if (!isset($defaults['caption'])) {
            $defaults['caption'] = $this->readableCaption($name);
        }

        /** @var UserAction\Generic $action */
        $action = $this->factory($this->_default_seed_action, $defaults);

        $this->_addIntoCollection($name, $action, 'actions');

        return $action;
    }

    /**
     * Returns list of actions for this model. Can filter actions by scope.
     * It will also skip system actions (where system === true).
     *
     * @param int $scope e.g. UserAction::ALL_RECORDS
     */
    public function getActions($scope = null): array
    {
        return array_filter($this->actions, function ($action) use ($scope) {
            return !$action->system && ($scope === null || $action->scope === $scope);
        });
    }

    /**
     * Returns true if user action with a corresponding name exists.
     *
     * @param string $name Action name
     */
    public function hasAction($name): bool
    {
        return $this->_hasInCollection($name, 'actions');
    }

    /**
     * Returns one action object of this model. If action not defined, then throws exception.
     *
     * @param string $name Action name
     */
    public function getAction($name): UserAction\Generic
    {
        return $this->_getFromCollection($name, 'actions');
    }

    /**
     * Execute specified action with specified arguments.
     *
     * @param string $name Action name
     */
    public function executeAction($name, ...$args)
    {
        $this->getAction($name)->execute(...$args);
    }

    /**
     * Remove specified action(s).
     *
     * @param string|array $name
     *
     * @return $this
     */
    public function removeAction($name)
    {
        foreach ((array) $name as $action) {
            $this->_removeFromCollection($action, 'actions');
        }

        return $this;
    }

    // }}}

    // {{{ DataSet logic

    /**
     * Narrow down data-set of the current model by applying
     * additional condition. There is no way to remove
     * condition once added, so if you need - clone model.
     *
     * This is the most basic for defining condition:
     *  ->addCondition('my_field', $value);
     *
     * This condition will work across all persistence drivers universally.
     *
     * In some cases a more complex logic can be used:
     *  ->addCondition('my_field', '>', $value);
     *  ->addCondition('my_field', '!=', $value);
     *  ->addCondition('my_field', 'in', [$value1, $value2]);
     *
     * Second argument could be '=', '>', '<', '>=', '<=', '!=', 'in', 'like' or 'regexp'.
     * Those conditions are still supported by most of persistence drivers.
     *
     * There are also vendor-specific expression support:
     *  ->addCondition('my_field', $expr);
     *  ->addCondition($expr);
     *
     * To use those, you should consult with documentation of your
     * persistence driver.
     *
     * @param mixed $field
     * @param mixed $operator
     * @param mixed $value
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        if (is_array($field)) {
            $this->conditions[] = [$field];

            return $this;
            /*
            $or = $this->persistence->orExpr();

            foreach ($field as list($field, $operator, $value)) {
                if (is_string($field)) {
                    $f = $this->getField($field);
                } elseif ($field instanceof Field) {
                    $f = $field;
                }

                $or->where($f, $operator, $value);
            }

            return $this;
            */
        }

        if (is_string($field)) {
            $f = $this->getField($field);
        } else {
            $f = $field;
        }

        if ($f instanceof Field) {
            if ($operator === '=' || func_num_args() === 2) {
                $v = ($operator === '=' ? $value : $operator);

                if (!is_object($v) && !is_array($v)) {
                    $f->system = true;
                    $f->default = $v;
                }
            }
        }

        $this->conditions[] = func_get_args();

        return $this;
    }

    /**
     * Shortcut for using addCondition(id_field, $id).
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function withID($id)
    {
        return $this->addCondition($this->id_field, $id);
    }

    /**
     * Adds WITH cursor.
     *
     * @param Model $model
     *
     * @return $this
     */
    public function addWith(self $model, string $alias, array $mapping = [], bool $recursive = false)
    {
        if (isset($this->with[$alias])) {
            throw (new Exception('With cursor already set with this alias'))
                ->addMoreInfo('alias', $alias);
        }

        $this->with[$alias] = [
            'model' => $model,
            'mapping' => $mapping,
            'recursive' => $recursive,
        ];

        return $this;
    }

    /**
     * Set order for model records. Multiple calls.
     *
     * @param mixed     $field
     * @param bool|null $desc
     *
     * @return $this
     */
    public function setOrder($field, $desc = null)
    {
        // fields passed as CSV string
        if (is_string($field) && strpos($field, ',') !== false) {
            $field = explode(',', $field);
        }

        // fields passed as array
        if (is_array($field)) {
            if ($desc !== null) {
                throw (new Exception('If first argument is array, second argument must not be used'))
                    ->addMoreInfo('arg1', $field)
                    ->addMoreInfo('arg2', $desc);
            }

            foreach (array_reverse($field) as $key => $o) {
                if (is_int($key)) {
                    if (is_array($o)) {
                        // format [field,order]
                        $this->setOrder(...$o);
                    } else {
                        // format "field order"
                        $this->setOrder($o);
                    }
                } else {
                    // format "field"=>order
                    $this->setOrder($key, $o);
                }
            }

            return $this;
        }

        // extract sort order from field name string
        if ($desc === null && is_string($field)) {
            // no realistic workaround in PHP for 2nd argument being null
            $field = trim($field);
            if (strpos($field, ' ') !== false) {
                list($field, $desc) = array_map('trim', explode(' ', $field, 2));
            }
        }

        // finally set order
        $this->order[] = [$field, $desc];

        return $this;
    }

    /**
     * Set limit of DataSet.
     *
     * @param int      $count
     * @param int|null $offset
     *
     * @return $this
     */
    public function setLimit($count, $offset = null)
    {
        $this->limit = [$count, $offset];

        return $this;
    }

    // }}}

    // {{{ Persistence-related logic

    /**
     * Is model loaded?
     *
     * @return bool
     */
    public function loaded()
    {
        return $this->id !== null;
    }

    /**
     * Unload model.
     *
     * @return $this
     */
    public function unload()
    {
        $this->hook(self::HOOK_BEFORE_UNLOAD);
        $this->id = null;
        $this->data = [];
        $this->dirty = [];
        $this->hook(self::HOOK_AFTER_UNLOAD);

        return $this;
    }

    /**
     * Load model.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function load($id, Persistence $from_persistence = null)
    {
        if (!$from_persistence) {
            $from_persistence = $this->persistence;
        }

        if (!$from_persistence) {
            throw new Exception('Model is not associated with any database');
        }

        if ($this->loaded()) {
            $this->unload();
        }

        if ($this->hook(self::HOOK_BEFORE_LOAD, [$id]) === false) {
            return $this;
        }

        $this->data = $from_persistence->load($this, $id);
        if ($this->id === null) {
            $this->id = $id;
        }

        $ret = $this->hook(self::HOOK_AFTER_LOAD);
        if ($ret === false) {
            return $this->unload();
        } elseif (is_object($ret)) {
            return $ret;
        }

        return $this;
    }

    /**
     * Reload model by taking its current ID.
     *
     * @return $this
     */
    public function reload()
    {
        $id = $this->id;
        $this->unload();

        return $this->load($id);
    }

    /**
     * Keeps the model data, but wipes out the ID so
     * when you save it next time, it ends up as a new
     * record in the database.
     *
     * @param mixed|null $new_id
     *
     * @return $this
     */
    public function duplicate($new_id = null)
    {
        $this->id = null;

        if ($this->id_field) {
            $this->set($this->id_field, $new_id);
        }

        return $this;
    }

    /**
     * Saves the current record by using a different
     * model class. This is similar to:.
     *
     * $m2 = $m->newInstance($class);
     * $m2->load($m->id);
     * $m2->set($m->get());
     * $m2->save();
     *
     * but will assume that both models are compatible,
     * therefore will not perform any loading.
     */
    public function saveAs(string $class, array $options = []): self
    {
        return $this->asModel($class, $options)->save();
    }

    /**
     * Store the data into database, but will never attempt to
     * reload the data. Additionally any data will be unloaded.
     * Use this instead of save() if you want to squeeze a
     * little more performance out.
     *
     * @param array $data
     *
     * @return $this
     */
    public function saveAndUnload($data = [])
    {
        $ras = $this->reload_after_save;
        $this->reload_after_save = false;
        $this->save($data);
        $this->unload();

        // restore original value
        $this->reload_after_save = $ras;

        return $this;
    }

    /**
     * This will cast Model into another class without
     * loosing state of your active record.
     */
    public function asModel(string $class, array $options = []): self
    {
        $m = $this->newInstance($class, $options);

        foreach ($this->data as $field => $value) {
            if ($value !== null && $value !== $this->getField($field)->default) {
                // Copying only non-default value
                $m->set($field, $value);
            }
        }

        // next we need to go over fields to see if any system
        // values have changed and mark them as dirty

        return $m;
    }

    /**
     * Create new model from the same base class
     * as $this.
     *
     * @return static
     */
    public function newInstance(string $class = null, array $options = [])
    {
        $model = $this->factory([$class ?? static::class], $options);

        if ($this->persistence) {
            return $this->persistence->add($model);
        }

        return $model;
    }

    /**
     * Create new model from the same base class
     * as $this. If you omit $id,then when saving
     * a new record will be created with default ID.
     * If you specify $id then it will be used
     * to save/update your record. If set $id
     * to `true` then model will assume that there
     * is already record like that in the destination
     * persistence.
     *
     * If you wish to fully copy the data from one
     * model to another you should use:
     *
     * $m->withPersistence($p2, false)->set($m->get())->save();
     *
     * See https://github.com/atk4/data/issues/111 for
     * use-case examples.
     *
     * @param Persistence $persistence
     * @param mixed       $id
     * @param string      $class
     *
     * @return $this
     */
    public function withPersistence($persistence, $id = null, string $class = null)
    {
        if (!$persistence instanceof Persistence) {
            throw (new Exception('Please supply valid persistence'))
                ->addMoreInfo('arg', $persistence);
        }

        if (!$class) {
            $class = static::class;
        }

        $m = new $class($persistence);

        if ($this->id_field) {
            if ($id === true) {
                $m->id = $this->id;
                $m->set($m->id_field, $this->get($this->id_field));
            } elseif ($id) {
                $m->id = null; // record shouldn't exist yet
                $m->set($m->id_field, $id);
            }
        }

        $m->data = $this->data;
        $m->dirty = $this->dirty;

        return $m;
    }

    /**
     * Try to load record.
     * Will not throw exception if record doesn't exist.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function tryLoad($id)
    {
        if (!$this->persistence) {
            throw new Exception('Model is not associated with any database');
        }

        if (!$this->persistence->hasMethod('tryLoad')) {
            throw new Exception('Persistence does not support tryLoad()');
        }

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->tryLoad($this, $id);
        if ($this->data) {
            $this->id = $id;

            $ret = $this->hook(self::HOOK_AFTER_LOAD);
            if ($ret === false) {
                return $this->unload();
            } elseif (is_object($ret)) {
                return $ret;
            }
        } else {
            $this->unload();
        }

        return $this;
    }

    /**
     * Load any record.
     *
     * @return $this
     */
    public function loadAny()
    {
        if (!$this->persistence) {
            throw new Exception('Model is not associated with any database');
        }

        if (!$this->persistence->hasMethod('loadAny')) {
            throw new Exception('Persistence does not support loadAny()');
        }

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->loadAny($this);
        if ($this->data) {
            if ($this->id_field) {
                $this->id = $this->data[$this->id_field];
            }

            $ret = $this->hook(self::HOOK_AFTER_LOAD);
            if ($ret === false) {
                return $this->unload();
            } elseif (is_object($ret)) {
                return $ret;
            }
        } else {
            $this->unload();
        }

        return $this;
    }

    /**
     * Try to load any record.
     * Will not throw exception if record doesn't exist.
     *
     * @return $this
     */
    public function tryLoadAny()
    {
        if (!$this->persistence) {
            throw new Exception('Model is not associated with any database');
        }

        if (!$this->persistence->hasMethod('tryLoadAny')) {
            throw new Exception('Persistence does not support tryLoadAny()');
        }

        if ($this->loaded()) {
            $this->unload();
        }

        $this->data = $this->persistence->tryLoadAny($this);
        if ($this->data) {
            if ($this->id_field) {
                if (isset($this->data[$this->id_field])) {
                    $this->id = $this->data[$this->id_field];
                }
            }

            $ret = $this->hook(self::HOOK_AFTER_LOAD);
            if ($ret === false) {
                return $this->unload();
            } elseif (is_object($ret)) {
                return $ret;
            }
        } else {
            $this->unload();
        }

        return $this;
    }

    /**
     * Load record by condition.
     *
     * @param mixed $field_name
     * @param mixed $value
     *
     * @return $this
     */
    public function loadBy(string $field_name, $value)
    {
        // store
        $field = $this->getField($field_name);
        $system = $field->system;
        $default = $field->default;

        // add condition and load record
        $this->addCondition($field_name, $value);

        try {
            $this->loadAny();
        } catch (\Exception $e) {
            // restore
            array_pop($this->conditions);
            $field->system = $system;
            $field->default = $default;

            throw $e;
        }

        // restore
        array_pop($this->conditions);
        $field->system = $system;
        $field->default = $default;

        return $this;
    }

    /**
     * Try to load record by condition.
     * Will not throw exception if record doesn't exist.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function tryLoadBy(string $field_name, $value)
    {
        // store
        $field_name = $this->getField($field_name);
        $system = $field_name->system;
        $default = $field_name->default;

        // add condition and try to load record
        $this->addCondition($field_name, $value);

        try {
            $this->tryLoadAny();
        } catch (\Exception $e) {
            // restore
            array_pop($this->conditions);
            $field_name->system = $system;
            $field_name->default = $default;

            throw $e;
        }

        // restore
        array_pop($this->conditions);
        $field_name->system = $system;
        $field_name->default = $default;

        return $this;
    }

    /**
     * Save record.
     *
     * @param array $data
     *
     * @return $this
     */
    public $_dirty_after_reload = [];

    public function save($data = [], Persistence $to_persistence = null)
    {
        if (!$to_persistence) {
            $to_persistence = $this->persistence;
        }

        if (!$to_persistence) {
            throw new Exception('Model is not associated with any database');
        }

        if ($this->read_only) {
            throw new Exception('Model is read-only and cannot be saved');
        }

        if ($data) {
            $this->set($data);
        }

        return $this->atomic(function () use ($to_persistence) {
            if (($errors = $this->validate('save')) !== []) {
                throw new ValidationException($errors, $this);
            }
            $is_update = $this->loaded();
            if ($this->hook(self::HOOK_BEFORE_SAVE, [$is_update]) === false) {
                return $this;
            }

            if ($is_update) {
                $data = [];
                $dirty_join = false;
                foreach ($this->dirty as $name => $junk) {
                    if (!$this->hasField($name)) {
                        continue;
                    }

                    $field = $this->getField($name);
                    if ($field->read_only || $field->never_persist || $field->never_save) {
                        continue;
                    }

                    // get the value of the field
                    $value = $this->get($name);

                    if (isset($field->join)) {
                        $dirty_join = true;
                        // storing into a different table join
                        $field->join->set($name, $value);
                    } else {
                        $data[$name] = $value;
                    }
                }

                // No save needed, nothing was changed
                if (!$data && !$dirty_join) {
                    return $this;
                }

                if ($this->hook(self::HOOK_BEFORE_UPDATE, [&$data]) === false) {
                    return $this;
                }

                $to_persistence->update($this, $this->id, $data);

                $this->hook(self::HOOK_AFTER_UPDATE, [&$data]);
            } else {
                $data = [];
                foreach ($this->get() as $name => $value) {
                    if (!$this->hasField($name)) {
                        continue;
                    }

                    $field = $this->getField($name);
                    if ($field->read_only || $field->never_persist || $field->never_save) {
                        continue;
                    }

                    if (isset($field->join)) {
                        // storing into a different table join
                        $field->join->set($name, $value);
                    } else {
                        $data[$name] = $value;
                    }
                }

                if ($this->hook(self::HOOK_BEFORE_INSERT, [&$data]) === false) {
                    return $this;
                }

                // Collect all data of a new record
                $this->id = $to_persistence->insert($this, $data);

                if (!$this->id_field) {
                    // Model inserted without any ID fields. Theoretically
                    // we should ignore $this->id even if it was returned.
                    $this->id = null;
                    $this->hook(self::HOOK_AFTER_INSERT, [null]);

                    $this->dirty = [];
                } elseif ($this->id) {
                    $this->set($this->id_field, $this->id);
                    $this->hook(self::HOOK_AFTER_INSERT, [$this->id]);

                    if ($this->reload_after_save !== false) {
                        $d = $this->dirty;
                        $this->dirty = [];
                        $this->reload();
                        $this->_dirty_after_reload = $this->dirty;
                        $this->dirty = $d;
                    }
                }
            }

            $this->hook(self::HOOK_AFTER_SAVE, [$is_update]);

            if ($this->loaded()) {
                $this->dirty = $this->_dirty_after_reload;
            }

            return $this;
        }, $to_persistence);
    }

    /**
     * This is a temporary method to avoid code duplication, but insert / import should
     * be implemented differently.
     *
     * @param Model $m   Model where to insert
     * @param array $row Data row to insert
     */
    protected function _rawInsert(self $m, array $row)
    {
        $m->reload_after_save = false;
        $m->unload();

        // Find any row values that do not correspond to fields, and they may correspond to
        // references instead
        $refs = [];
        foreach ($row as $key => $value) {
            // and we only support array values
            if (!is_array($value)) {
                continue;
            }

            // and reference must exist with same name
            if (!$this->hasRef($key)) {
                continue;
            }

            // Then we move value for later
            $refs[$key] = $value;
            unset($row[$key]);
        }

        // save data fields
        $m->save($row);

        // store id value
        if ($this->id_field) {
            $m->data[$m->id_field] = $m->id;
        }

        // if there was referenced data, then import it
        foreach ($refs as $key => $value) {
            $m->ref($key)->import($value);
        }
    }

    /**
     * Faster method to add data, that does not modify active record.
     *
     * Will be further optimized in the future.
     *
     * @return mixed
     */
    public function insert(array $row)
    {
        $m = clone $this;
        $this->_rawInsert($m, $row);

        return $m->id;
    }

    /**
     * Even more faster method to add data, does not modify your
     * current record and will not return anything.
     *
     * Will be further optimized in the future.
     *
     * @return $this
     */
    public function import(array $rows)
    {
        $m = clone $this;
        foreach ($rows as $row) {
            $this->_rawInsert($m, $row);
        }

        return $this;
    }

    /**
     * Export DataSet as array of hashes.
     *
     * @param array|null $fields        Names of fields to export
     * @param string     $key_field     Optional name of field which value we will use as array key
     * @param bool       $typecast_data Should we typecast exported data
     */
    public function export($fields = null, $key_field = null, $typecast_data = true): array
    {
        if (!$this->persistence->hasMethod('export')) {
            throw new Exception('Persistence does not support export()');
        }

        // no key field - then just do export
        if ($key_field === null) {
            return $this->persistence->export($this, $fields, $typecast_data);
        }

        // do we have added key field in fields list?
        // if so, then will have to remove it afterwards
        $key_field_added = false;

        // prepare array with field names
        if (!is_array($fields)) {
            $fields = [];

            if ($this->only_fields) {
                // Add requested fields first
                foreach ($this->only_fields as $field) {
                    $f_object = $this->getField($field);
                    if ($f_object->never_persist) {
                        continue;
                    }
                    $fields[$field] = true;
                }

                // now add system fields, if they were not added
                foreach ($this->getFields() as $field => $f_object) {
                    if ($f_object->never_persist) {
                        continue;
                    }
                    if ($f_object->system && !isset($fields[$field])) {
                        $fields[$field] = true;
                    }
                }

                $fields = array_keys($fields);
            } else {
                // Add all model fields
                foreach ($this->getFields() as $field => $f_object) {
                    if ($f_object->never_persist) {
                        continue;
                    }
                    $fields[] = $field;
                }
            }
        }

        // add key_field to array if it's not there
        if (!in_array($key_field, $fields, true)) {
            $fields[] = $key_field;
            $key_field_added = true;
        }

        // export
        $data = $this->persistence->export($this, $fields, $typecast_data);

        // prepare resulting array
        $res = [];
        foreach ($data as $row) {
            $key = $row[$key_field];
            if ($key_field_added) {
                unset($row[$key_field]);
            }
            $res[$key] = $row;
        }

        return $res;
    }

    /**
     * Returns iterator (yield values).
     *
     * @return mixed
     */
    public function getIterator()
    {
        foreach ($this->rawIterator() as $data) {
            $this->data = $this->persistence->typecastLoadRow($this, $data);
            if ($this->id_field) {
                $this->id = $data[$this->id_field] ?? null;
            }

            // you can return false in afterLoad hook to prevent to yield this data row
            // use it like this:
            // $model->onHook(self::HOOK_AFTER_LOAD, function ($m) {
            //     if ($m->get('date') < $m->date_from) $m->breakHook(false);
            // })

            // you can also use breakHook() with specific object which will then be returned
            // as a next iterator value

            $ret = $this->hook(self::HOOK_AFTER_LOAD);

            if ($ret === false) {
                continue;
            }

            if (is_object($ret)) {
                if ($ret->id_field) {
                    yield $ret->id => $ret;
                } else {
                    yield $ret;
                }
            } else {
                if ($this->id_field) {
                    yield $this->id => $this;
                } else {
                    yield $this;
                }
            }
        }

        $this->unload();
    }

    /**
     * Returns iterator.
     *
     * @return Iterator
     */
    public function rawIterator()
    {
        return $this->persistence->prepareIterator($this);
    }

    /**
     * Executes specified method or callback for each record in DataSet.
     *
     * @param string|callable $method
     *
     * @return $this
     */
    public function each($method)
    {
        foreach ($this as $rec) {
            if (is_string($method)) {
                $rec->{$method}();
            } elseif (is_callable($method)) {
                call_user_func($method, $rec);
            }
        }

        return $this;
    }

    /**
     * Delete record with a specified id. If no ID is specified
     * then current record is deleted.
     *
     * @param mixed $id
     *
     * @return $this
     */
    public function delete($id = null)
    {
        if ($this->read_only) {
            throw new Exception('Model is read-only and cannot be deleted');
        }

        if ($id == $this->id) {
            $id = null;
        }

        return $this->atomic(function () use ($id) {
            if ($id) {
                $c = clone $this;
                $c->load($id)->delete();

                return $this;
            } elseif ($this->loaded()) {
                if ($this->hook(self::HOOK_BEFORE_DELETE, [$this->id]) === false) {
                    return $this;
                }
                $this->persistence->delete($this, $this->id);
                $this->hook(self::HOOK_AFTER_DELETE, [$this->id]);
                $this->unload();

                return $this;
            }

            throw new Exception('No active record is set, unable to delete.');
        });
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @param callable $f
     *
     * @return mixed
     */
    public function atomic($f, Persistence $persistence = null)
    {
        if (!$persistence) {
            $persistence = $this->persistence;
        }

        try {
            return $persistence->atomic($f);
        } catch (\Exception $e) {
            if ($this->hook(self::HOOK_ROLLBACK, [$this, $e]) !== false) {
                throw $e;
            }
        }
    }

    // }}}

    // {{{ Support for actions

    /**
     * Execute action.
     *
     * @param string $mode
     * @param array  $args
     *
     * @return Query
     */
    public function action($mode, $args = [])
    {
        if (!$this->persistence) {
            throw new Exception('action() requires model to be associated with db');
        }

        if (!$this->persistence->hasMethod('action')) {
            throw new Exception('Persistence does not support action()');
        }

        return $this->persistence->action($this, $mode, $args);
    }

    // }}}

    // {{{ Join support

    /**
     * Creates an objects that describes relationship between multiple tables (or collections).
     *
     * When object is loaded, then instead of pulling all the data from a single table,
     * join will also query $foreign_table in order to find additional fields. When inserting
     * the record will be also added inside $foreign_table and relationship will be maintained.
     *
     * @param string $foreign_table
     * @param array  $defaults
     *
     * @return Join
     */
    public function join($foreign_table, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        } elseif (isset($defaults[0])) {
            $defaults['master_field'] = $defaults[0];
            unset($defaults[0]);
        }

        $defaults[0] = $foreign_table;

        $c = $this->_default_seed_join;

        return $this->add($this->factory($c, $defaults));
    }

    /**
     * Left Join support.
     *
     * @see join()
     *
     * @param string $foreign_table
     * @param array  $defaults
     *
     * @return Join
     */
    public function leftJoin($foreign_table, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        }
        $defaults['weak'] = true;

        return $this->join($foreign_table, $defaults);
    }

    // }}}

    // {{{ References

    /**
     * Private method.
     *
     * @param string         $c        Class name
     * @param string         $link     Link
     * @param array|callable $defaults Properties which we will pass to Reference object constructor
     */
    protected function _hasReference($c, $link, $defaults = []): Reference
    {
        if (!is_array($defaults)) {
            $defaults = ['model' => $defaults ?: 'Model_' . $link];
        } elseif (isset($defaults[0])) {
            $defaults['model'] = $defaults[0];
            unset($defaults[0]);
        }

        $defaults[0] = $link;

        $obj = $this->factory($c, $defaults);

        // if reference with such name already exists, then throw exception
        if ($this->hasElement($name = $obj->getDesiredName())) {
            throw (new Exception('Reference with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('link', $link)
                ->addMoreInfo('defaults', $defaults);
        }

        return $this->add($obj);
    }

    /**
     * Add generic relation. Provide your own call-back that will
     * return the model.
     *
     * @param string         $link     Link
     * @param array|callable $callback Callback
     */
    public function addRef($link, $callback): Reference
    {
        return $this->_hasReference($this->_default_seed_addRef, $link, $callback);
    }

    /**
     * Add hasOne field.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\HasOne
     */
    public function hasOne($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_hasOne, $link, $defaults);
    }

    /**
     * Add hasMany field.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\HasMany
     */
    public function hasMany($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_hasMany, $link, $defaults);
    }

    /**
     * Add containsOne field.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\ContainsOne
     */
    public function containsOne($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_containsOne, $link, $defaults);
    }

    /**
     * Add containsMany field.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\ContainsMany
     */
    public function containsMany($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_containsMany, $link, $defaults);
    }

    /**
     * Traverse to related model.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Model
     */
    public function ref($link, $defaults = []): self
    {
        return $this->getRef($link)->ref($defaults);
    }

    /**
     * Return related model.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Model
     */
    public function refModel($link, $defaults = []): self
    {
        return $this->getRef($link)->refModel($defaults);
    }

    /**
     * Returns model that can be used for generating sub-query actions.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Model
     */
    public function refLink($link, $defaults = []): self
    {
        return $this->getRef($link)->refLink($defaults);
    }

    /**
     * Return reference field.
     *
     * @param string $link
     */
    public function getRef($link): Reference
    {
        return $this->getElement('#ref_' . $link);
    }

    /**
     * Returns all reference fields.
     */
    public function getRefs(): array
    {
        $refs = [];
        foreach ($this->elements as $key => $val) {
            if (substr($key, 0, 5) === '#ref_') {
                $refs[substr($key, 5)] = $val;
            }
        }

        return $refs;
    }

    /**
     * Returns true if reference field exists.
     *
     * @param string $link
     */
    public function hasRef($link): bool
    {
        return $this->hasElement('#ref_' . $link);
    }

    // }}}

    // {{{ Expressions

    /**
     * Add expression field.
     *
     * @param string                $name
     * @param string|array|callable $expression
     *
     * @return Field\Callback
     */
    public function addExpression($name, $expression)
    {
        if (!is_array($expression)) {
            $expression = ['expr' => $expression];
        } elseif (isset($expression[0])) {
            $expression['expr'] = $expression[0];
            unset($expression[0]);
        }

        $c = $this->_default_seed_addExpression;

        $field = $this->factory($c, $expression);

        $this->addField($name, $field);

        return $field;
    }

    /**
     * Add expression field which will calculate its value by using callback.
     *
     * @param string                $name
     * @param string|array|callable $expression
     *
     * @return Field\Callback
     */
    public function addCalculatedField($name, $expression)
    {
        if (!is_array($expression)) {
            $expression = ['expr' => $expression];
        } elseif (isset($expression[0])) {
            $expression['expr'] = $expression[0];
            unset($expression[0]);
        }

        return $this->addField($name, new Field\Callback($expression));
    }

    // }}}

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [
            'id' => $this->id,
            'conditions' => $this->conditions,
        ];

        return $arr;
    }

    // }}}
}
