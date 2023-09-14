<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\CollectionTrait;
use Atk4\Core\ContainerTrait;
use Atk4\Core\DiContainerTrait;
use Atk4\Core\DynamicMethodTrait;
use Atk4\Core\Exception as CoreException;
use Atk4\Core\Factory;
use Atk4\Core\HookBreaker;
use Atk4\Core\HookTrait;
use Atk4\Core\InitializerTrait;
use Atk4\Core\ReadableCaptionTrait;
use Atk4\Data\Field\CallbackField;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model\Scope\AbstractScope;
use Atk4\Data\Model\Scope\RootScope;
use Mvorisek\Atk4\Hintable\Data\HintableModelTrait;

/**
 * @property int                                       $id       @Atk4\Field() Contains ID of the current record.
 *                                                               If the value is null then the record is considered to be new.
 * @property array<string, Field|Reference|Model\Join> $elements
 *
 * @phpstan-implements \IteratorAggregate<static>
 */
class Model implements \IteratorAggregate
{
    use CollectionTrait {
        _addIntoCollection as private __addIntoCollection;
    }
    use ContainerTrait {
        add as private _add;
    }
    use DiContainerTrait {
        DiContainerTrait::__isset as private __di_isset;
        DiContainerTrait::__get as private __di_get;
        DiContainerTrait::__set as private __di_set;
        DiContainerTrait::__unset as private __di_unset;
    }
    use DynamicMethodTrait;
    use HintableModelTrait {
        HintableModelTrait::assertIsInitialized as private __hintable_assertIsInitialized;
        HintableModelTrait::__isset as private __hintable_isset;
        HintableModelTrait::__get as private __hintable_get;
        HintableModelTrait::__set as private __hintable_set;
        HintableModelTrait::__unset as private __hintable_unset;
    }
    use HookTrait;
    use InitializerTrait {
        init as private _init;
        InitializerTrait::assertIsInitialized as private _assertIsInitialized;
    }
    use Model\JoinsTrait;
    use Model\ReferencesTrait;
    use Model\UserActionsTrait;
    use ReadableCaptionTrait;

    public const HOOK_BEFORE_LOAD = self::class . '@beforeLoad';
    public const HOOK_AFTER_LOAD = self::class . '@afterLoad';
    public const HOOK_BEFORE_UNLOAD = self::class . '@beforeUnload';
    public const HOOK_AFTER_UNLOAD = self::class . '@afterUnload';

    public const HOOK_BEFORE_INSERT = self::class . '@beforeInsert';
    public const HOOK_AFTER_INSERT = self::class . '@afterInsert';
    public const HOOK_BEFORE_UPDATE = self::class . '@beforeUpdate';
    public const HOOK_AFTER_UPDATE = self::class . '@afterUpdate';
    public const HOOK_BEFORE_DELETE = self::class . '@beforeDelete';
    public const HOOK_AFTER_DELETE = self::class . '@afterDelete';

    public const HOOK_BEFORE_SAVE = self::class . '@beforeSave';
    public const HOOK_AFTER_SAVE = self::class . '@afterSave';

    /** Executed when execution of self::atomic() failed. */
    public const HOOK_ROLLBACK = self::class . '@rollback';

    /** Executed for every field set using self::set() method. */
    public const HOOK_NORMALIZE = self::class . '@normalize';
    /** Executed when self::validate() method is called. */
    public const HOOK_VALIDATE = self::class . '@validate';
    /** Executed when self::setOnlyFields() method is called. */
    public const HOOK_ONLY_FIELDS = self::class . '@onlyFields';

    protected const ID_LOAD_ONE = self::class . '@idLoadOne-h7axmDNBB3qVXjVv';
    protected const ID_LOAD_ANY = self::class . '@idLoadAny-h7axmDNBB3qVXjVv';

    /** @var static|null not-null if and only if this instance is an entity */
    private ?self $_model = null;

    /** @var mixed once set, loading a different ID will result in an error */
    private $_entityId;

    /** @var array<string, true> */
    private static $_modelOnlyProperties;

    /** @var array<mixed> The seed used by addField() method. */
    protected $_defaultSeedAddField = [Field::class];

    /** @var array<mixed> The seed used by addExpression() method. */
    protected $_defaultSeedAddExpression = [CallbackField::class];

    /** @var array<string, Field> */
    protected array $fields = [];

    /**
     * Contains name of table, session key, collection or file where this
     * model normally lives. The interpretation of the table will be decoded
     * by persistence driver.
     *
     * @var string|self|false
     */
    public $table;

    /** @var string|null */
    public $tableAlias;

    /** @var Persistence|null */
    private $_persistence;

    /** @var array<string, mixed>|null Persistence store some custom information in here that may be useful for them. */
    public ?array $persistenceData = null;

    /** @var RootScope */
    private $scope;

    /** @var array{int|null, int} */
    public array $limit = [null, 0];

    /** @var array<int, array{string|Persistence\Sql\Expressionable, 'asc'|'desc'}> */
    public array $order = [];

    /** @var array<string, array{'model': Model, 'recursive': bool}> */
    public array $cteModels = [];

    /**
     * Currently loaded record data. This record is associative array
     * that contain field => data pairs. It may contain data for un-defined
     * fields only if $onlyFields mode is false.
     *
     * Avoid accessing $data directly, use set() / get() instead.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * After loading an entity the data will be stored in
     * $data property and you can access them using get(). If you use
     * set() to change any of the data, the original value will be copied
     * here.
     *
     * If the value you set equal to the original value, then the key
     * in this array will be removed.
     *
     * @var array<string, mixed>
     */
    private array $dirty = [];

    /**
     * Setting model as readOnly will protect you from accidentally
     * updating the model. This property is intended for UI and other code
     * detecting read-only models and acting accordingly.
     */
    public bool $readOnly = false;

    /**
     * While in most cases your id field will be called 'id', sometimes
     * you would want to use a different one or maybe don't create field
     * at all.
     *
     * @var string|false
     */
    public $idField = 'id';

    /**
     * Title field is used typically by UI components for a simple human
     * readable row title/description.
     */
    public ?string $titleField = 'name';

    /**
     * Caption of the model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string|null
     */
    public $caption;

    /**
     * When using setOnlyFields() this property will contain list of desired
     * fields.
     *
     * If you set setOnlyFields() before loading the data for this model, then
     * only that set of fields will be available. Attempt to access any other
     * field will result in exception. This is to ensure that you do not
     * accidentally access field that you have explicitly excluded.
     *
     * The default behavior is to return NULL and allow you to set new
     * fields even if addField() was not used to set the field.
     *
     * setOnlyFields() always allows to access fields with system = true.
     *
     * @var array<int, string>|null
     */
    public ?array $onlyFields = null;

    /**
     * Models that contain expressions will automatically reload after save.
     * This is to ensure that any SQL-based calculation are executed and
     * updated correctly after you have performed any modifications to
     * the fields.
     */
    public bool $reloadAfterSave = true;

    /**
     * If this model is "contained into" another entity by using ContainsOne
     * or ContainsMany reference, then this property will contain reference
     * to owning entity.
     */
    public ?self $containedInEntity = null;

    /** Only for Reference class */
    public ?Reference $ownerReference = null;

    // {{{ Basic Functionality, field definition, set() and get()

    /**
     * Creation of the new model can be done in two ways:.
     *
     * $m = new Model($db);
     *   or
     * $m = new Model();
     * $m->setPersistence($db);
     *
     * The second use actually calls add() but is preferred usage because:
     *  - it's shorter
     *  - type hinting will work;
     *  - you can specify string for a table
     *
     * @param array<string, mixed> $defaults
     */
    public function __construct(Persistence $persistence = null, array $defaults = [])
    {
        $this->scope = \Closure::bind(static function () {
            return new RootScope();
        }, null, RootScope::class)()
            ->setModel($this);

        $this->setDefaults($defaults);

        if ($persistence !== null) {
            $this->setPersistence($persistence);
        }
    }

    public function isEntity(): bool
    {
        return $this->_model !== null;
    }

    public function assertIsModel(self $expectedModelInstance = null): void
    {
        if ($this->_model !== null) {
            throw new \TypeError('Expected model, but instance is an entity');
        }

        if ($expectedModelInstance !== null && $expectedModelInstance !== $this) {
            $expectedModelInstance->assertIsModel();

            throw new \TypeError('Model instance does not match');
        }
    }

    public function assertIsEntity(self $expectedModelInstance = null): void
    {
        if ($this->_model === null) {
            throw new \TypeError('Expected entity, but instance is a model');
        }

        if ($expectedModelInstance !== null) {
            $this->getModel()->assertIsModel($expectedModelInstance);
        }
    }

    /**
     * @return static
     */
    public function getModel(bool $allowOnModel = false): self
    {
        if ($this->_model !== null) {
            return $this->_model;
        }

        if (!$allowOnModel) {
            $this->assertIsEntity();
        }

        return $this;
    }

    public function __clone()
    {
        if (!$this->isEntity()) {
            $this->scope = (clone $this->scope)->setModel($this);
            $this->_cloneCollection('fields');
            $this->_cloneCollection('elements');
        }
        $this->_cloneCollection('userActions');

        // check for clone errors immediately, otherwise not strictly needed
        $this->_rebindHooksIfCloned();
    }

    /**
     * @return array<string, true>
     */
    protected function getModelOnlyProperties(): array
    {
        $this->assertIsModel();

        if (self::$_modelOnlyProperties === null) {
            $modelOnlyProperties = [];
            foreach ((new \ReflectionClass(self::class))->getProperties() as $prop) {
                if (!$prop->isStatic()) {
                    $modelOnlyProperties[$prop->getName()] = true;
                }
            }

            $modelOnlyProperties = array_diff_key($modelOnlyProperties, array_flip([
                '_model',
                '_entityId',
                'data',
                'dirty',

                'hooks',
                '_hookIndexCounter',
                '_hookOrigThis',

                'ownerReference', // should be removed once references are non-entity
                'userActions', // should be removed once user actions are non-entity

                'containedInEntity',
            ]));

            self::$_modelOnlyProperties = $modelOnlyProperties;
        }

        return self::$_modelOnlyProperties;
    }

    /**
     * @return static
     */
    public function createEntity(): self
    {
        $this->assertIsModel();

        $userActionsBackup = $this->userActions;
        try {
            $this->_model = $this;
            $this->userActions = [];
            $entity = clone $this;
        } finally {
            $this->_model = null;
            $this->userActions = $userActionsBackup;
        }
        $entity->_entityId = null;

        // unset non-entity properties, they are magically remapped to the model when accessed
        foreach (array_keys($this->getModelOnlyProperties()) as $name) {
            unset($entity->{$name});
        }

        return $entity;
    }

    /**
     * Extend this method to define fields of your choice.
     */
    protected function init(): void
    {
        $this->assertIsModel();

        $this->_init();

        if ($this->idField) {
            $this->addField($this->idField, ['type' => 'integer', 'required' => true, 'system' => true]);

            $this->initEntityIdHooks();

            if (!$this->readOnly) {
                $this->initUserActions();
            }
        }
    }

    public function assertIsInitialized(): void
    {
        $this->_assertIsInitialized();
        $this->__hintable_assertIsInitialized();
    }

    private function initEntityIdAndAssertUnchanged(): void
    {
        $id = $this->getId();
        if ($id === null) { // allow unload
            return;
        }

        if ($this->_entityId === null) {
            // set entity ID to the first seen ID
            $this->_entityId = $id;
        } elseif ($this->_entityId !== $id && !$this->compare($this->idField, $this->_entityId)) {
            $this->unload(); // data for different ID were loaded, make sure to discard them

            throw (new Exception('Model instance is an entity, ID cannot be changed to a different one'))
                ->addMoreInfo('entityId', $this->_entityId)
                ->addMoreInfo('newId', $id);
        }
    }

    private function initEntityIdHooks(): void
    {
        $fx = function () {
            $this->initEntityIdAndAssertUnchanged();
        };

        $this->onHookShort(self::HOOK_BEFORE_LOAD, $fx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_LOAD, $fx, [], -10);
        $this->onHookShort(self::HOOK_BEFORE_DELETE, $fx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_DELETE, $fx, [], -10);
        $this->onHookShort(self::HOOK_BEFORE_SAVE, $fx, [], 10);
        $this->onHookShort(self::HOOK_AFTER_SAVE, $fx, [], -10);
    }

    /**
     * @param Field|Reference|Model\Join $obj
     * @param array<string, mixed>       $defaults
     */
    public function add(object $obj, array $defaults = []): void
    {
        $this->assertIsModel();

        if ($obj instanceof Field) {
            throw new Exception('Field can be added using addField() method only');
        }

        $this->_add($obj, $defaults);
    }

    public function _addIntoCollection(string $name, object $item, string $collection): object
    {
        // TODO $this->assertIsModel();

        return $this->__addIntoCollection($name, $item, $collection);
    }

    /**
     * @return array<string, mixed>
     *
     * @internal should be not used outside atk4/data
     */
    public function &getDataRef(): array
    {
        $this->assertIsEntity();

        return $this->data;
    }

    /**
     * @return array<string, mixed>
     *
     * @internal should be not used outside atk4/data
     */
    public function &getDirtyRef(): array
    {
        $this->assertIsEntity();

        return $this->dirty;
    }

    /**
     * Perform validation on a currently loaded values, must return Array in format:
     *  ['field' => 'must be 4 digits exactly'] or empty array if no errors were present.
     *
     * You may also use format:
     *  ['field' => ['must not have character [ch]', 'ch' => $badCharacter]] for better localization of error message.
     *
     * Always use
     *   return array_merge(parent::validate($intent), $errors);
     *
     * @param string $intent by default only 'save' is used (from beforeSave) but you can use other intents yourself
     *
     * @return array<string, string> [field => err_spec]
     */
    public function validate(string $intent = null): array
    {
        $errors = [];
        foreach ($this->hook(self::HOOK_VALIDATE, [$intent]) as $error) {
            if ($error) {
                $errors = array_merge($errors, $error);
            }
        }

        return $errors;
    }

    /** @var array<string, array<mixed>> */
    protected array $fieldSeedByType = [];

    /**
     * Given a field seed, return a field object.
     *
     * @param array<mixed> $seed
     */
    protected function fieldFactory(array $seed = []): Field
    {
        $seed = Factory::mergeSeeds(
            $seed,
            isset($seed['type']) ? ($this->fieldSeedByType[$seed['type']] ?? null) : null,
            $this->_defaultSeedAddField
        );

        return Field::fromSeed($seed);
    }

    /**
     * Adds new field into model.
     *
     * @param array<mixed>|object $seed
     */
    public function addField(string $name, $seed = []): Field
    {
        $this->assertIsModel();

        if (is_object($seed)) {
            $field = $seed;
        } else {
            $field = $this->fieldFactory($seed);
        }

        return $this->_addIntoCollection($name, $field, 'fields');
    }

    /**
     * Adds multiple fields into model.
     *
     * @param array<string, array<mixed>|object>|array<int, string> $fields
     * @param array<mixed>                                          $seed
     *
     * @return $this
     */
    public function addFields(array $fields, array $seed = [])
    {
        foreach ($fields as $k => $v) {
            if (is_int($k)) {
                $k = $v;
                $v = [];
            }

            $this->addField($k, Factory::mergeSeeds($v, $seed));
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
        $this->assertIsModel();

        $this->getField($name); // better exception if field does not exist

        $this->_removeFromCollection($name, 'fields');

        return $this;
    }

    public function hasField(string $name): bool
    {
        if ($this->isEntity()) {
            return $this->getModel()->hasField($name);
        }

        return $this->_hasInCollection($name, 'fields');
    }

    public function getField(string $name): Field
    {
        if ($this->isEntity()) {
            return $this->getModel()->getField($name);
        }

        try {
            return $this->_getFromCollection($name, 'fields');
        } catch (CoreException $e) {
            throw (new Exception('Field is not defined'))
                ->addMoreInfo('model', $this)
                ->addMoreInfo('field', $name);
        }
    }

    /**
     * Sets which fields we will select.
     *
     * @param array<int, string>|null $fields
     *
     * @return $this
     */
    public function setOnlyFields(?array $fields)
    {
        $this->assertIsModel();

        $this->hook(self::HOOK_ONLY_FIELDS, [&$fields]);
        $this->onlyFields = $fields;

        return $this;
    }

    private function assertOnlyField(string $field): void
    {
        $this->assertIsModel();

        $this->getField($field); // assert field exists

        if ($this->onlyFields !== null) {
            if (!in_array($field, $this->onlyFields, true) && !$this->getField($field)->system) {
                throw (new Exception('Attempt to use field outside of those set by setOnlyFields'))
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('onlyFields', $this->onlyFields);
            }
        }
    }

    /**
     * Will return true if specified field is dirty.
     */
    public function isDirty(string $field): bool
    {
        $this->getModel()->assertOnlyField($field);

        $dirtyRef = &$this->getDirtyRef();
        if (array_key_exists($field, $dirtyRef)) {
            return true;
        }

        return false;
    }

    /**
     * @param string|array<int, string>|null $filter
     *
     * @return array<string, Field>
     */
    public function getFields($filter = null): array
    {
        if ($this->isEntity()) {
            return $this->getModel()->getFields($filter);
        }

        if ($filter === null) {
            return $this->fields;
        } elseif (is_string($filter)) {
            $filter = [$filter];
        }

        return array_filter($this->fields, function (Field $field, $name) use ($filter) {
            // do not return fields outside of "onlyFields" scope
            if ($this->onlyFields !== null && !in_array($name, $this->onlyFields, true)) { // TODO also without filter?
                return false;
            }
            foreach ($filter as $f) {
                if (($f === 'system' && $field->system)
                    || ($f === 'not system' && !$field->system)
                    || ($f === 'editable' && $field->isEditable())
                    || ($f === 'visible' && $field->isVisible())
                ) {
                    return true;
                } elseif (!in_array($f, ['system', 'not system', 'editable', 'visible'], true)) {
                    throw (new Exception('Field filter is not supported'))
                        ->addMoreInfo('filter', $f);
                }
            }

            return false;
        }, \ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Set field value.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function set(string $field, $value)
    {
        $this->getModel()->assertOnlyField($field);

        $f = $this->getField($field);

        if (!$value instanceof Persistence\Sql\Expressionable) {
            try {
                $value = $f->normalize($value);
            } catch (Exception $e) {
                $e->addMoreInfo('field', $f);
                $e->addMoreInfo('value', $value);

                throw $e;
            }
        }

        // do nothing when value has not changed
        $dataRef = &$this->getDataRef();
        $dirtyRef = &$this->getDirtyRef();
        $currentValue = array_key_exists($field, $dataRef)
            ? $dataRef[$field]
            : (array_key_exists($field, $dirtyRef) ? $dirtyRef[$field] : $f->default);
        if (!$value instanceof Persistence\Sql\Expressionable && $f->compare($value, $currentValue)) {
            return $this;
        }

        if ($f->readOnly) {
            throw (new Exception('Attempting to change read-only field'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('model', $this);
        }

        if (array_key_exists($field, $dirtyRef) && $f->compare($dirtyRef[$field], $value)) {
            unset($dirtyRef[$field]);
        } elseif (!array_key_exists($field, $dirtyRef)) {
            $dirtyRef[$field] = array_key_exists($field, $dataRef) ? $dataRef[$field] : $f->default;
        }
        $dataRef[$field] = $value;

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     *
     * @return $this
     */
    public function setNull(string $field)
    {
        // set temporary hook to disable any normalization (null validation)
        $hookIndex = $this->getModel()->onHookShort(self::HOOK_NORMALIZE, static function () {
            throw new HookBreaker(false);
        }, [], \PHP_INT_MIN);
        try {
            return $this->set($field, null);
        } finally {
            $this->getModel()->removeHook(self::HOOK_NORMALIZE, $hookIndex, true);
        }
    }

    /**
     * Helper method to call self::set() for each input array element.
     *
     * This method does not revert the data when an exception is thrown.
     *
     * @param array<string, mixed> $fields
     *
     * @return $this
     */
    public function setMulti(array $fields)
    {
        foreach ($fields as $field => $value) {
            $this->set($field, $value);
        }

        return $this;
    }

    /**
     * Returns field value.
     * If no field is passed, then returns array of all field values.
     *
     * @return ($field is null ? array<string, mixed> : mixed)
     */
    public function get(string $field = null)
    {
        if ($field === null) {
            $this->assertIsEntity();

            $data = [];
            foreach ($this->onlyFields ?? array_keys($this->getFields()) as $k) {
                $data[$k] = $this->get($k);
            }

            return $data;
        }

        $this->getModel()->assertOnlyField($field);

        $data = $this->getDataRef();
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        return $this->getField($field)->default;
    }

    private function assertHasIdField(): void
    {
        if (!is_string($this->idField) || !$this->hasField($this->idField)) {
            throw new Exception('ID field is not defined');
        }
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        try {
            return $this->get($this->getModel()->idField);
        } catch (\Throwable $e) {
            $this->assertHasIdField();

            throw $e;
        }
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setId($value, bool $allowNull = true)
    {
        try {
            if ($value === null && $allowNull) {
                $this->setNull($this->getModel()->idField);
            } else {
                $this->set($this->getModel()->idField, $value);
            }

            $this->initEntityIdAndAssertUnchanged();

            return $this;
        } catch (\Throwable $e) {
            $this->assertHasIdField();

            throw $e;
        }
    }

    /**
     * Return (possibly localized) $model->caption.
     * If caption is not set, then generate it from model class name.
     */
    public function getModelCaption(): string
    {
        return $this->caption ?? $this->readableCaption(get_debug_type($this));
    }

    /**
     * Return value of $model->get($model->titleField). If not set, returns id value.
     *
     * @return mixed
     */
    public function getTitle()
    {
        if ($this->titleField && $this->hasField($this->titleField)) {
            return $this->get($this->titleField);
        }

        return $this->getId();
    }

    /**
     * Returns array of model record titles [id => title].
     *
     * @return array<int|string, mixed>
     */
    public function getTitles(): array
    {
        $this->assertIsModel();

        $field = $this->titleField && $this->hasField($this->titleField) ? $this->titleField : $this->idField;

        return array_map(static function (array $row) use ($field) {
            return $row[$field];
        }, $this->export([$field], $this->idField));
    }

    /**
     * @param mixed $value
     */
    public function compare(string $name, $value): bool
    {
        $value2 = $this->get($name);

        if ($value === $value2) { // optimization only
            return true;
        }

        return $this->getField($name)->compare($value, $value2);
    }

    /**
     * Does field exist?
     */
    public function _isset(string $name): bool
    {
        $this->getModel()->assertOnlyField($name);

        $dirtyRef = &$this->getDirtyRef();

        return array_key_exists($name, $dirtyRef);
    }

    /**
     * Remove current field value and use default.
     *
     * @return $this
     */
    public function _unset(string $name)
    {
        $this->getModel()->assertOnlyField($name);

        $dataRef = &$this->getDataRef();
        $dirtyRef = &$this->getDirtyRef();
        if (array_key_exists($name, $dirtyRef)) {
            $dataRef[$name] = $dirtyRef[$name];
            unset($dirtyRef[$name]);
        }

        return $this;
    }

    // }}}

    // {{{ Model logic

    /**
     * Get the scope object of the Model.
     */
    public function scope(): RootScope
    {
        $this->assertIsModel();

        return $this->scope;
    }

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
     * Conditions on referenced models are also supported:
     *  $contact->addCondition('company/country', 'US');
     * where 'company' is the name of the reference
     * This will limit scope of $contact model to contacts whose company country is set to 'US'
     *
     * Using # in conditions on referenced model will apply the condition on the number of records:
     * $contact->addCondition('tickets/#', '>', 5);
     * This will limit scope of $contact model to contacts that have more than 5 tickets
     *
     * To use those, you should consult with documentation of your
     * persistence driver.
     *
     * @param AbstractScope|array<int, AbstractScope|Persistence\Sql\Expressionable|array{string|Persistence\Sql\Expressionable, 1?: mixed, 2?: mixed}>|string|Persistence\Sql\Expressionable $field
     * @param ($field is string|Persistence\Sql\Expressionable ? ($value is null ? mixed : string) : never)                                                                                   $operator
     * @param ($operator is string ? mixed : never)                                                                                                                                           $value
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        $this->scope()->addCondition(...'func_get_args'());

        return $this;
    }

    /**
     * Adds WITH/CTE model.
     *
     * @return $this
     */
    public function addCteModel(string $name, self $model, bool $recursive = false)
    {
        if ($name === $this->table || $name === $this->tableAlias || isset($this->cteModels[$name])) {
            throw (new Exception('CTE model with given name already exist'))
                ->addMoreInfo('name', $name);
        }

        $this->cteModels[$name] = [
            'model' => $model,
            'recursive' => $recursive,
        ];

        return $this;
    }

    /**
     * Set order for model records. Multiple calls are allowed.
     *
     * @param string|array<int, string|array{string, 1?: 'asc'|'desc'}>|array<string, 'asc'|'desc'> $field
     * @param 'asc'|'desc'                                                                          $direction
     *
     * @return $this
     */
    public function setOrder($field, string $direction = 'asc')
    {
        $this->assertIsModel();

        // fields passed as array
        if (is_array($field)) {
            if ('func_num_args'() > 1) {
                throw (new Exception('If first argument is array, second argument must not be used'))
                    ->addMoreInfo('arg1', $field)
                    ->addMoreInfo('arg2', $direction);
            }

            foreach (array_reverse($field) as $k => $v) {
                if (is_int($k)) {
                    if (is_array($v)) {
                        // format [field, direction]
                        $this->setOrder(...$v);
                    } else {
                        // format "field"
                        $this->setOrder($v);
                    }
                } else {
                    // format "field" => direction
                    $this->setOrder($k, $v);
                }
            }

            return $this;
        }

        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw (new Exception('Invalid order direction, direction can be only "asc" or "desc"'))
                ->addMoreInfo('field', $field)
                ->addMoreInfo('direction', $direction);
        }

        // finally set order
        $this->order[] = [$field, $direction];

        return $this;
    }

    /**
     * Set limit of DataSet.
     *
     * @return $this
     */
    public function setLimit(int $count = null, int $offset = 0)
    {
        $this->assertIsModel();

        $this->limit = [$count, $offset];

        return $this;
    }

    // }}}

    // {{{ Persistence-related logic

    public function issetPersistence(): bool
    {
        $this->assertIsModel();

        return $this->_persistence !== null;
    }

    public function getPersistence(): Persistence
    {
        $this->assertIsModel();

        return $this->_persistence;
    }

    /**
     * @return $this
     */
    public function setPersistence(Persistence $persistence)
    {
        if ($this->issetPersistence()) {
            throw new Exception('Persistence is already set');
        }

        if ($this->persistenceData === []) {
            $this->_persistence = $persistence;
        } else {
            $this->persistenceData = [];
            $persistence->add($this);
        }

        $this->getPersistence(); // assert persistence is set

        return $this;
    }

    public function assertHasPersistence(string $methodName = null): void
    {
        if (!$this->issetPersistence()) {
            throw new Exception('Model is not associated with a persistence');
        }

        if ($methodName !== null && !$this->getPersistence()->hasMethod($methodName)) {
            throw new Exception('Persistence does not support "' . $methodName . '" method');
        }
    }

    /**
     * Is entity loaded?
     */
    public function isLoaded(): bool
    {
        return $this->getModel()->idField && $this->getId() !== null && $this->_entityId !== null;
    }

    public function assertIsLoaded(): void
    {
        if (!$this->isLoaded()) {
            throw new Exception('Expected loaded entity');
        }
    }

    /**
     * @return $this
     */
    public function unload()
    {
        $this->assertIsEntity();

        $this->hook(self::HOOK_BEFORE_UNLOAD);
        $dataRef = &$this->getDataRef();
        $dirtyRef = &$this->getDirtyRef();
        $dataRef = [];
        if ($this->idField) {
            $this->setId(null);
        }
        $dirtyRef = [];
        $this->hook(self::HOOK_AFTER_UNLOAD);

        return $this;
    }

    /**
     * @param mixed $id
     *
     * @return mixed
     */
    private function remapIdLoadToPersistence($id)
    {
        if ($id === self::ID_LOAD_ONE) {
            return Persistence::ID_LOAD_ONE;
        } elseif ($id === self::ID_LOAD_ANY) {
            return Persistence::ID_LOAD_ANY;
        }

        return $id;
    }

    /**
     * @param ($fromTryLoad is true ? false : bool) $fromReload
     * @param mixed                                 $id
     *
     * @return ($fromTryLoad is true ? static|null : static)
     */
    private function _load(bool $fromReload, bool $fromTryLoad, $id)
    {
        $this->getModel()->assertHasPersistence();
        if ($this->isLoaded()) {
            throw new Exception('Entity must be unloaded');
        }

        $noId = $id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY;
        $res = $this->hook(self::HOOK_BEFORE_LOAD, [$noId ? null : $id]);
        if ($res === false) {
            if ($fromReload) {
                $this->unload();

                return $this;
            }

            return null;
        } elseif (is_object($res)) {
            $res = (static::class)::assertInstanceOf($res);
            $res->assertIsEntity();

            return $res;
        }

        $data = $this->getModel()->getPersistence()->{$fromTryLoad ? 'tryLoad' : 'load'}($this->getModel(), $this->remapIdLoadToPersistence($id));
        if ($data === null) {
            return null; // $fromTryLoad is always true here
        }

        $dataRef = &$this->getDataRef();
        $dataRef = $data;

        if ($this->idField) {
            $this->setId($data[$this->idField], false);
        }

        $res = $this->hook(self::HOOK_AFTER_LOAD);
        if ($res === false) {
            if ($fromReload) {
                $this->unload();

                return $this;
            }

            return null;
        } elseif (is_object($res)) {
            $res = (static::class)::assertInstanceOf($res);
            $res->assertIsEntity();

            return $res;
        }

        return $this;
    }

    /**
     * Try to load record. Will not throw an exception if record does not exist.
     *
     * @param mixed $id
     *
     * @return static|null
     */
    public function tryLoad($id)
    {
        $this->assertIsModel();

        return $this->createEntity()->_load(false, true, $id);
    }

    /**
     * Load one record by an ID.
     *
     * @param mixed $id
     *
     * @return static
     */
    public function load($id)
    {
        $this->assertIsModel();

        return $this->createEntity()->_load(false, false, $id);
    }

    /**
     * Try to load one record. Will throw if more than one record exists, but not if there is no record.
     *
     * @return static|null
     */
    public function tryLoadOne()
    {
        return $this->tryLoad(self::ID_LOAD_ONE);
    }

    /**
     * Load one record. Will throw if more than one record exists.
     *
     * @return static
     */
    public function loadOne()
    {
        return $this->load(self::ID_LOAD_ONE);
    }

    /**
     * Try to load any record. Will not throw an exception if record does not exist.
     *
     * If only one record should match, use checked "tryLoadOne" method.
     *
     * @return static|null
     */
    public function tryLoadAny()
    {
        return $this->tryLoad(self::ID_LOAD_ANY);
    }

    /**
     * Load any record.
     *
     * If only one record should match, use checked "loadOne" method.
     *
     * @return static
     */
    public function loadAny()
    {
        return $this->load(self::ID_LOAD_ANY);
    }

    /**
     * Reload model by taking its current ID.
     *
     * @return $this
     */
    public function reload()
    {
        $id = $this->getId();
        $data = $this->getDataRef(); // keep weakly persisted objects referenced
        $this->unload();

        $res = $this->_load(true, false, $id);
        if ($res !== $this) {
            throw new Exception('Entity instance does not match');
        }

        return $this;
    }

    /**
     * Keeps the model data, but wipes out the ID so
     * when you save it next time, it ends up as a new
     * record in the database.
     *
     * @return static
     */
    public function duplicate()
    {
        $this->assertIsEntity();

        $duplicate = clone $this;
        $duplicate->_entityId = null;
        $data = $this->getDataRef();
        $duplicateDirtyRef = &$duplicate->getDirtyRef();
        $duplicateDirtyRef = $data;
        $duplicate->setId(null);

        return $duplicate;
    }

    /**
     * Store the data into database, but will never attempt to
     * reload the data. Additionally any data will be unloaded.
     * Use this instead of save() if you want to squeeze a
     * little more performance out.
     *
     * @param array<string, mixed> $data
     *
     * @return $this
     */
    public function saveAndUnload(array $data = [])
    {
        $reloadAfterSaveBackup = $this->reloadAfterSave;
        try {
            $this->getModel()->reloadAfterSave = false;
            $this->save($data);
        } finally {
            $this->getModel()->reloadAfterSave = $reloadAfterSaveBackup;
        }

        $this->unload();

        return $this;
    }

    /**
     * Create new model from the same base class as $this.
     *
     * See https://github.com/atk4/data/issues/111 for use-case examples.
     *
     * @return static
     */
    public function withPersistence(Persistence $persistence)
    {
        $this->assertIsModel();

        $model = new static($persistence, ['table' => $this->table]);

        // include any fields defined inline
        foreach ($this->fields as $fieldName => $field) {
            if (!$model->hasField($fieldName)) {
                $model->addField($fieldName, clone $field);
            }
        }

        $model->limit = $this->limit;
        $model->order = $this->order;
        $model->scope = (clone $this->scope)->setModel($model);

        return $model;
    }

    /**
     * TODO https://github.com/atk4/data/issues/662.
     *
     * @return array<string, array{bool, mixed}>
     */
    private function temporaryMutateScopeFieldsBackup(): array
    {
        $res = [];
        $fields = $this->getFields();
        foreach ($fields as $k => $v) {
            $res[$k] = [$v->system, $v->default];
        }

        return $res;
    }

    /**
     * @param array<string, array{bool, mixed}> $backup
     */
    private function temporaryMutateScopeFieldsRestore(array $backup): void
    {
        $fields = $this->getFields();
        foreach ($fields as $k => $v) {
            [$v->system, $v->default] = $backup[$k];
        }
    }

    /**
     * @param AbstractScope|array<int, AbstractScope|Persistence\Sql\Expressionable|array{string|Persistence\Sql\Expressionable, 1?: mixed, 2?: mixed}>|string|Persistence\Sql\Expressionable $field
     * @param ($field is string|Persistence\Sql\Expressionable ? ($value is null ? mixed : string) : never)                                                                                   $operator
     * @param ($operator is string ? mixed : never)                                                                                                                                           $value
     *
     * @return ($fromTryLoad is true ? static|null : static)
     */
    private function _loadBy(bool $fromTryLoad, $field, $operator = null, $value = null)
    {
        $this->assertIsModel();

        $scopeOrig = $this->scope;
        $fieldsBackup = $this->temporaryMutateScopeFieldsBackup();
        $this->scope = clone $this->scope;
        try {
            $this->addCondition(...array_slice('func_get_args'(), 1));

            return $this->{$fromTryLoad ? 'tryLoadOne' : 'loadOne'}();
        } finally {
            $this->scope = $scopeOrig;
            $this->temporaryMutateScopeFieldsRestore($fieldsBackup);
        }
    }

    /**
     * Load one record by additional condition. Will throw if more than one record exists.
     *
     * @param AbstractScope|array<int, AbstractScope|Persistence\Sql\Expressionable|array{string|Persistence\Sql\Expressionable, 1?: mixed, 2?: mixed}>|string|Persistence\Sql\Expressionable $field
     * @param ($field is string|Persistence\Sql\Expressionable ? ($value is null ? mixed : string) : never)                                                                                   $operator
     * @param ($operator is string ? mixed : never)                                                                                                                                           $value
     *
     * @return static
     */
    public function loadBy($field, $operator = null, $value = null)
    {
        return $this->_loadBy(false, ...'func_get_args'());
    }

    /**
     * Try to load one record by additional condition. Will throw if more than one record exists, but not if there is no record.
     *
     * @param AbstractScope|array<int, AbstractScope|Persistence\Sql\Expressionable|array{string|Persistence\Sql\Expressionable, 1?: mixed, 2?: mixed}>|string|Persistence\Sql\Expressionable $field
     * @param ($field is string|Persistence\Sql\Expressionable ? ($value is null ? mixed : string) : never)                                                                                   $operator
     * @param ($operator is string ? mixed : never)                                                                                                                                           $value
     *
     * @return static|null
     */
    public function tryLoadBy($field, $operator = null, $value = null)
    {
        return $this->_loadBy(true, ...'func_get_args'());
    }

    protected function validateEntityScope(): void
    {
        if (!$this->getModel()->scope()->isEmpty()) {
            $this->getModel()->getPersistence()->load($this->getModel(), $this->getId());
        }
    }

    private function assertIsWriteable(): void
    {
        if ($this->readOnly) {
            throw new Exception('Model is read-only');
        }
    }

    /**
     * Save record.
     *
     * @param array<string, mixed> $data
     *
     * @return $this
     */
    public function save(array $data = [])
    {
        $this->getModel()->assertIsWriteable();
        $this->getModel()->assertHasPersistence();

        $this->setMulti($data);

        return $this->atomic(function () {
            $errors = $this->validate('save');
            if ($errors !== []) {
                throw new ValidationException($errors, $this);
            }
            $isUpdate = $this->isLoaded();
            if ($this->hook(self::HOOK_BEFORE_SAVE, [$isUpdate]) === false) {
                return $this;
            }

            if (!$isUpdate) {
                $data = [];
                foreach ($this->get() as $name => $value) {
                    $field = $this->getField($name);
                    if ($field->readOnly || $field->neverPersist || $field->neverSave) {
                        continue;
                    }

                    if (!$field->hasJoin()) {
                        $data[$name] = $value;
                    }
                }

                if ($this->hook(self::HOOK_BEFORE_INSERT, [&$data]) === false) {
                    return $this;
                }

                $id = $this->getModel()->getPersistence()->insert($this->getModel(), $data);
                if ($this->idField) {
                    $this->setId($id, false);
                }

                $this->hook(self::HOOK_AFTER_INSERT);
            } else {
                $data = [];
                $dirtyJoin = false;
                foreach ($this->get() as $name => $value) {
                    if (!array_key_exists($name, $this->getDirtyRef())) {
                        continue;
                    }

                    $field = $this->getField($name);
                    if ($field->readOnly || $field->neverPersist || $field->neverSave) {
                        continue;
                    }

                    if ($field->hasJoin()) {
                        $dirtyJoin = true;
                    } else {
                        $data[$name] = $value;
                    }
                }

                // no save needed, nothing was changed
                if (count($data) === 0 && !$dirtyJoin) {
                    return $this;
                }

                if ($this->hook(self::HOOK_BEFORE_UPDATE, [&$data]) === false) {
                    return $this;
                }
                $this->validateEntityScope();
                $this->getModel()->getPersistence()->update($this->getModel(), $this->getId(), $data);
                $this->hook(self::HOOK_AFTER_UPDATE, [&$data]);
            }

            $dirtyRef = &$this->getDirtyRef();
            $dirtyRef = [];

            if ($this->idField && $this->reloadAfterSave) {
                $this->reload();
            }

            $this->hook(self::HOOK_AFTER_SAVE, [$isUpdate]);

            if ($this->idField) {
                $this->validateEntityScope();
            }

            return $this;
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function _insert(array $row): void
    {
        // find any row values that do not correspond to fields, they may correspond to references instead
        $refs = [];
        foreach ($row as $key => $value) {
            if (!is_array($value) || !$this->hasReference($key)) {
                continue;
            }

            // then we move value for later
            $refs[$key] = $value;
            unset($row[$key]);
        }

        // save data fields
        $reloadAfterSaveBackup = $this->reloadAfterSave;
        try {
            $this->getModel()->reloadAfterSave = false;
            $this->save($row);
        } finally {
            $this->getModel()->reloadAfterSave = $reloadAfterSaveBackup;
        }

        // if there was referenced data, then import it
        foreach ($refs as $key => $value) {
            $this->ref($key)->import($value);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return mixed
     */
    public function insert(array $row)
    {
        $entity = $this->createEntity();

        $hasArrayValue = false;
        foreach ($row as $v) {
            if (is_array($v)) {
                $hasArrayValue = true;

                break;
            }
        }

        if (!$hasArrayValue) {
            $entity->_insert($row);
        } else {
            $this->atomic(static function () use ($entity, $row) {
                $entity->_insert($row);
            });
        }

        return $this->idField ? $entity->getId() : null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return $this
     */
    public function import(array $rows)
    {
        if (count($rows) === 1) {
            $this->insert(reset($rows));
        } elseif (count($rows) !== 0) {
            $this->atomic(function () use ($rows) {
                foreach ($rows as $row) {
                    $this->insert($row);
                }
            });
        }

        return $this;
    }

    /**
     * Export DataSet as array of hashes.
     *
     * @param array<int, string>|null $fields   Names of fields to export
     * @param string                  $keyField Optional name of field which value we will use as array key
     * @param bool                    $typecast Should we typecast exported data
     *
     * @return ($keyField is string ? array<mixed, array<string, mixed>> : array<int, array<string, mixed>>)
     */
    public function export(array $fields = null, string $keyField = null, bool $typecast = true): array
    {
        $this->assertHasPersistence('export');

        // no key field - then just do export
        if ($keyField === null) {
            // TODO this optimization should be removed in favor of one Persistence::export call and php calculated fields should be exported as well
            return $this->getPersistence()->export($this, $fields, $typecast);
        }

        // do we have added key field in fields list?
        // if so, then will have to remove it afterwards
        $keyFieldAdded = false;

        // prepare array with field names
        if ($fields === null) {
            $fields = [];

            if ($this->onlyFields !== null) {
                // add requested fields first
                foreach ($this->onlyFields as $field) {
                    $fObject = $this->getField($field);
                    if ($fObject->neverPersist) {
                        continue;
                    }
                    $fields[$field] = true;
                }

                // now add system fields, if they were not added
                foreach ($this->getFields() as $field => $fObject) {
                    if ($fObject->neverPersist) {
                        continue;
                    }
                    if ($fObject->system && !isset($fields[$field])) {
                        $fields[$field] = true;
                    }
                }

                $fields = array_keys($fields);
            } else {
                // add all model fields
                foreach ($this->getFields() as $field => $fObject) {
                    if ($fObject->neverPersist) {
                        continue;
                    }
                    $fields[] = $field;
                }
            }
        }

        // add $keyField to array if it's not there
        if (!in_array($keyField, $fields, true)) {
            $fields[] = $keyField;
            $keyFieldAdded = true;
        }

        // export
        $data = $this->getPersistence()->export($this, $fields, $typecast);

        // prepare resulting array
        $res = [];
        foreach ($data as $row) {
            $key = $row[$keyField];
            if ($keyFieldAdded) {
                unset($row[$keyField]);
            }
            $res[$key] = $row;
        }

        return $res;
    }

    /**
     * Create iterator (yield values).
     *
     * You can return false in afterLoad hook to prevent to yield this data row, example:
     * $model->onHook(self::HOOK_AFTER_LOAD, static function (Model $m) {
     *     if ($m->get('date') < $m->dateFrom) {
     *         $m->breakHook(false);
     *     }
     * })
     *
     * You can also use breakHook() with specific object which will then be returned
     * as a next iterator value.
     *
     * @return \Traversable<static>
     */
    final public function getIterator(): \Traversable
    {
        return $this->createIteratorBy([]);
    }

    /**
     * Create iterator (yield values) by additional condition.
     *
     * @param AbstractScope|array<int, AbstractScope|Persistence\Sql\Expressionable|array{string|Persistence\Sql\Expressionable, 1?: mixed, 2?: mixed}>|string|Persistence\Sql\Expressionable $field
     * @param ($field is string|Persistence\Sql\Expressionable ? ($value is null ? mixed : string) : never)                                                                                   $operator
     * @param ($operator is string ? mixed : never)                                                                                                                                           $value
     *
     * @return \Traversable<static>
     */
    public function createIteratorBy($field, $operator = null, $value = null): \Traversable
    {
        $this->assertIsModel();

        $scopeOrig = null;
        if ((!is_array($field) || count($field) > 0) || $operator !== null || $value !== null) {
            $scopeOrig = $this->scope;
            $fieldsBackup = $this->temporaryMutateScopeFieldsBackup();
            $this->scope = clone $this->scope;
        }
        try {
            if ($scopeOrig !== null) {
                $this->addCondition(...'func_get_args'());
            }

            foreach ($this->getPersistence()->prepareIterator($this) as $data) {
                if ($scopeOrig !== null) {
                    $this->scope = $scopeOrig;
                    $scopeOrig = null;
                    $this->temporaryMutateScopeFieldsRestore($fieldsBackup); // @phpstan-ignore-line https://github.com/phpstan/phpstan/issues/9685
                }

                $entity = $this->createEntity();

                $dataRef = &$entity->getDataRef();
                $dataRef = $this->getPersistence()->typecastLoadRow($this, $data);
                if ($this->idField) {
                    $entity->setId($dataRef[$this->idField], false);
                }

                $res = $entity->hook(self::HOOK_AFTER_LOAD);
                if ($res === false) {
                    continue;
                } elseif (is_object($res)) {
                    $res = (static::class)::assertInstanceOf($res);
                    $res->assertIsEntity();
                } else {
                    $res = $entity;
                }

                if ($res->getModel()->idField) {
                    yield $res->getId() => $res;
                } else {
                    yield $res;
                }
            }
        } finally {
            if ($scopeOrig !== null) {
                $this->scope = $scopeOrig;
                $scopeOrig = null;
                $this->temporaryMutateScopeFieldsRestore($fieldsBackup); // @phpstan-ignore-line https://github.com/phpstan/phpstan/issues/9685
            }
        }
    }

    /**
     * Delete record with a specified id. If no ID is specified
     * then current record is deleted.
     *
     * @param mixed $id
     *
     * @return static
     */
    public function delete($id = null)
    {
        if ($id !== null) {
            $this->assertIsModel();

            $this->load($id)->delete();

            return $this;
        }

        $this->getModel()->assertIsWriteable();
        $this->getModel()->assertHasPersistence();
        $this->assertIsLoaded();

        $this->atomic(function () {
            if ($this->hook(self::HOOK_BEFORE_DELETE) === false) {
                return;
            }
            $this->validateEntityScope();
            $this->getModel()->getPersistence()->delete($this->getModel(), $this->getId());
            $this->hook(self::HOOK_AFTER_DELETE);
        });
        $this->unload();

        return $this;
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @template T
     *
     * @param \Closure(): T $fx
     *
     * @return T
     */
    public function atomic(\Closure $fx)
    {
        try {
            return $this->getModel(true)->getPersistence()->atomic($fx);
        } catch (\Throwable $e) {
            if ($this->hook(self::HOOK_ROLLBACK, [$e]) === false) {
                return false;
            }

            throw $e;
        }
    }

    // }}}

    // {{{ Support for actions

    /**
     * Create persistence action.
     *
     * TODO Rename this method to stress this method should not be used
     * for anything else then reading records as insert/update/delete hooks
     * will not be called.
     *
     * @param array<mixed> $args
     *
     * @return Persistence\Sql\Query
     */
    public function action(string $mode, array $args = [])
    {
        $this->getModel(true)->assertHasPersistence('action');

        return $this->getModel(true)->getPersistence()->action($this, $mode, $args);
    }

    public function executeCountQuery(): int
    {
        $this->assertIsModel();

        $res = $this->action('count')->getOne();
        if (is_string($res) && $res === (string) (int) $res) {
            $res = (int) $res;
        }

        return $res;
    }

    /**
     * Add expression field.
     *
     * @param array{'expr': mixed} $seed
     *
     * @return CallbackField|SqlExpressionField
     */
    public function addExpression(string $name, $seed)
    {
        /** @var CallbackField|SqlExpressionField */
        $field = Field::fromSeed($this->_defaultSeedAddExpression, $seed);

        $this->addField($name, $field);

        return $field;
    }

    /**
     * Add expression field which will calculate its value by using callback.
     *
     * @template T of self
     *
     * @param array{'expr': \Closure(T): mixed} $seed
     *
     * @return CallbackField
     */
    public function addCalculatedField(string $name, $seed)
    {
        $field = new CallbackField($seed);

        $this->addField($name, $field);

        return $field;
    }

    public function __isset(string $name): bool
    {
        $model = $this->getModel(true);

        if (isset($model->getHintableProps()[$name])) {
            return $this->__hintable_isset($name);
        }

        if ($this->isEntity() && isset($model->getModelOnlyProperties()[$name])) {
            return isset($model->{$name});
        }

        return $this->__di_isset($name);
    }

    /**
     * @return mixed
     */
    public function &__get(string $name)
    {
        $model = $this->getModel(true);

        if (isset($model->getHintableProps()[$name])) {
            return $this->__hintable_get($name);
        }

        if ($this->isEntity() && isset($model->getModelOnlyProperties()[$name])) {
            return $model->{$name};
        }

        return $this->__di_get($name);
    }

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        $model = $this->getModel(true);

        if (isset($model->getHintableProps()[$name])) {
            $this->__hintable_set($name, $value);

            return;
        }

        if ($this->isEntity() && isset($model->getModelOnlyProperties()[$name])) {
            $this->assertIsModel();
        }

        $this->__di_set($name, $value);
    }

    public function __unset(string $name): void
    {
        $model = $this->getModel(true);

        if (isset($model->getHintableProps()[$name])) {
            $this->__hintable_unset($name);

            return;
        }

        if ($this->isEntity() && isset($model->getModelOnlyProperties()[$name])) {
            $this->assertIsModel();
        }

        $this->__di_unset($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        if ($this->isEntity()) {
            return [
                'entityId' => $this->idField && $this->hasField($this->idField)
                    ? ($this->_entityId !== null ? $this->_entityId . ($this->getId() !== null ? '' : ' (unloaded)') : 'null')
                    : 'no id field',
                'model' => $this->getModel()->__debugInfo(),
            ];
        }

        return [
            'table' => $this->table,
            'scope' => $this->scope()->toWords(),
        ];
    }
}
