<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Factory;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * Provides generic functionality for joining data.
 *
 * @method Model getOwner()
 */
abstract class Join
{
    use DiContainerTrait;
    use InitializerTrait {
        init as private _init;
    }
    use JoinLinkTrait;
    use TrackableTrait {
        setOwner as private _setOwner;
    }

    /**
     * Foreign table or WITH/CTE alias when used with SQL persistence.
     *
     * @var string
     */
    protected $foreign_table;

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join by passing ['kind' => 'right']
     * as second argument to join().
     *
     * @var string
     */
    protected $kind;

    /** @var bool Weak join does not update foreign table. */
    protected $weak = false;

    /**
     * Normally the foreign table is saved first, then it's ID is used in the
     * primary table. When deleting, the primary table record is deleted first
     * which is followed by the foreign table record.
     *
     * If you are using the following syntax:
     *
     * $user->join('contact', 'default_contact_id')
     *
     * Then the ID connecting tables is stored in foreign table and the order
     * of saving and delete needs to be reversed. In this case $reverse
     * will be set to `true`. You can specify value of this property.
     *
     * @var bool
     */
    protected $reverse;

    /**
     * Field to be used for matching inside master table.
     * By default it's $foreign_table . '_id'.
     *
     * @var string
     */
    protected $master_field;

    /**
     * Field to be used for matching in a foreign table.
     * By default it's 'id'.
     *
     * @var string
     */
    protected $foreign_field;

    /** @var string A short symbolic name that will be used as an alias for the joined table. */
    public $foreign_alias;

    /**
     * When $prefix is set, then all the fields generated through
     * our wrappers will be automatically prefixed inside the model.
     *
     * @var string
     */
    protected $prefix = '';

    /** @var mixed ID indexed by spl_object_id(entity) used by a joined table. */
    protected $idByOid;

    /** @var array<int, array<string, mixed>> Data indexed by spl_object_id(entity) which is populated here as the save/insert progresses. */
    private $saveBufferByOid = [];

    public function __construct(string $foreignTable = null)
    {
        $this->foreign_table = $foreignTable;

        // handle foreign table containing a dot - that will be reverse join
        // TODO this table split condition makes JoinArrayTest::testForeignFieldNameGuessTableWithSchema test
        // quite unconsistent - drop it?
        if (str_contains($this->foreign_table, '.')) {
            // split by LAST dot in foreign_table name
            [$this->foreign_table, $this->foreign_field] = preg_split('~\.+(?=[^.]+$)~', $this->foreign_table);
            $this->reverse = true;
        }
    }

    /**
     * Create fake foreign model, in the future, this method should be removed
     * in favor of always requiring an object model.
     */
    protected function createFakeForeignModel(): Model
    {
        $fakeModel = new Model($this->getOwner()->getPersistence(), [
            'table' => $this->foreign_table,
        ]);
        foreach ($this->getOwner()->getFields() as $ownerField) {
            if ($ownerField->hasJoin() && $ownerField->getJoin()->shortName === $this->shortName
                && $ownerField->shortName !== $fakeModel->id_field
                && $ownerField->shortName !== $this->foreign_field) {
                $fakeModel->addField($ownerField->shortName, [
                    'actual' => $ownerField->actual,
                    'type' => $ownerField->type,
                ]);
            }
        }
        if ($fakeModel->id_field !== $this->foreign_field && $this->foreign_field !== null) {
            $fakeModel->addField($this->foreign_field, ['type' => 'integer']);
        }

        return $fakeModel;
    }

    public function getForeignModel(): Model
    {
        // TODO this should be removed in the future
        if (!isset($this->getOwner()->with[$this->foreign_table])) {
            return $this->createFakeForeignModel();
        }

        return $this->getOwner()->with[$this->foreign_table]['model'];
    }

    /**
     * @param Model $owner
     *
     * @return $this
     */
    public function setOwner(object $owner)
    {
        $owner->assertIsModel();

        return $this->_setOwner($owner);
    }

    protected function onHookToOwnerBoth(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamic(
            $spot,
            static function (Model $model) use ($name): self {
                /** @var self */
                $obj = $model->getModel(true)->getElement($name);
                $model->getModel(true)->assertIsModel($obj->getOwner());

                return $obj;
            },
            $fx,
            $args,
            $priority
        );
    }

    protected function onHookToOwnerEntity(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamic(
            $spot,
            static function (Model $entity) use ($name): self {
                /** @var self */
                $obj = $entity->getModel()->getElement($name);
                $entity->assertIsEntity($obj->getOwner());

                return $obj;
            },
            $fx,
            $args,
            $priority
        );
    }

    private function getModelTableString(Model $model): string
    {
        if (is_object($model->table)) {
            return $this->getModelTableString($model->table);
        }

        return $model->table;
    }

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '#join_' . $this->foreign_table;
    }

    protected function init(): void
    {
        $this->_init();

        $this->getForeignModel(); // assert valid foreign_table

        // owner model should have id_field set
        $id_field = $this->getOwner()->id_field;
        if (!$id_field) {
            throw (new Exception('Joins owner model should have id_field set'))
                ->addMoreInfo('model', $this->getOwner());
        }

        if ($this->reverse === true) {
            if ($this->master_field && $this->master_field !== $id_field) { // TODO not implemented yet, see https://github.com/atk4/data/issues/803
                throw (new Exception('Joining tables on non-id fields is not implemented yet'))
                    ->addMoreInfo('master_field', $this->master_field)
                    ->addMoreInfo('id_field', $id_field);
            }

            if (!$this->master_field) {
                $this->master_field = $id_field;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = preg_replace('~^.+?\.~', '', $this->getModelTableString($this->getOwner())) . '_' . $id_field;
            }
        } else {
            $this->reverse = false;

            if (!$this->master_field) {
                $this->master_field = $this->foreign_table . '_' . $id_field;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = $id_field;
            }
        }

        // if kind is not specified, figure out join type
        if (!$this->kind) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        $this->initJoinHooks();
    }

    protected function initJoinHooks(): void
    {
        $this->onHookToOwnerEntity(Model::HOOK_AFTER_UNLOAD, \Closure::fromCallable([$this, 'afterUnload']));

        if ($this->reverse) {
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
        } else {
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));

            $this->onHookToOwnerEntity(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table, but
     * form the join instead.
     */
    public function addField(string $name, array $seed = []): Field
    {
        $seed['joinName'] = $this->shortName;

        return $this->getOwner()->addField($this->prefix . $name, $seed);
    }

    /**
     * Adds multiple fields.
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $defaults = [])
    {
        foreach ($fields as $name => $seed) {
            if (is_int($name)) {
                $name = $seed;
                $seed = [];
            }

            $this->addField($name, Factory::mergeSeeds($seed, $defaults));
        }

        return $this;
    }

    /**
     * Another join will be attached to a current join.
     *
     * @param array<string, mixed> $defaults
     */
    public function join(string $foreignTable, array $defaults = []): self
    {
        $defaults['joinName'] = $this->shortName;

        return $this->getOwner()->join($foreignTable, $defaults);
    }

    /**
     * Another leftJoin will be attached to a current join.
     *
     * @param array<string, mixed> $defaults
     */
    public function leftJoin(string $foreignTable, array $defaults = []): self
    {
        $defaults['joinName'] = $this->shortName;

        return $this->getOwner()->leftJoin($foreignTable, $defaults);
    }

    /**
     * Creates reference based on a field from the join.
     *
     * @return Reference\HasOne
     */
    public function hasOne(string $link, array $defaults = [])
    {
        $defaults['joinName'] = $this->shortName;

        return $this->getOwner()->hasOne($link, $defaults);
    }

    /**
     * Creates reference based on the field from the join.
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $link, array $defaults = [])
    {
        return $this->getOwner()->hasMany($link, $defaults);
    }

    /**
     * Wrapper for containsOne that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @return ???
     */
    /*
    public function containsOne(Model $model, array $defaults = [])
    {
        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsOne($model, $defaults);
    }
    */

    /**
     * Wrapper for containsMany that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @return ???
     */
    /*
    public function containsMany(Model $model, array $defaults = [])
    {
        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsMany($model, $defaults);
    }
    */

    /**
     * Will iterate through this model by pulling
     *  - fields
     *  - references
     *  - conditions.
     *
     * and then will apply them locally. If you think that any fields
     * could clash, then use ['prefix' => 'm2'] which will be pre-pended
     * to all the fields. Conditions will be automatically mapped.
     *
     * @todo NOT IMPLEMENTED !
     */
    /*
    public function importModel(Model $model, array $defaults = [])
    {
        // not implemented yet !!!
    }
    */

    /**
     * @param mixed $value
     */
    protected function assertReferenceIdNotNull($value): void
    {
        if ($value === null) {
            throw (new Exception('Unable to join on null value'))
                ->addMoreInfo('value', $value);
        }
    }

    /**
     * @return mixed
     *
     * @internal should be not used outside atk4/data
     */
    protected function getId(Model $entity)
    {
        return $this->idByOid[spl_object_id($entity)];
    }

    /**
     * @param mixed $id
     *
     * @internal should be not used outside atk4/data
     */
    protected function setId(Model $entity, $id): void
    {
        $this->assertReferenceIdNotNull($id);

        $this->idByOid[spl_object_id($entity)] = $id;
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function unsetId(Model $entity): void
    {
        unset($this->idByOid[spl_object_id($entity)]);
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function issetSaveBuffer(Model $entity): bool
    {
        return isset($this->saveBufferByOid[spl_object_id($entity)]);
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function getAndUnsetSaveBuffer(Model $entity): array
    {
        $res = $this->saveBufferByOid[spl_object_id($entity)];
        $this->unsetSaveBuffer($entity);

        return $res;
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function unsetSaveBuffer(Model $entity): void
    {
        unset($this->saveBufferByOid[spl_object_id($entity)]);
    }

    /**
     * @param mixed $value
     */
    public function setSaveBufferValue(Model $entity, string $fieldName, $value): void
    {
        $entity->assertIsEntity($this->getOwner());

        if (!isset($this->saveBufferByOid[spl_object_id($entity)])) {
            $this->saveBufferByOid[spl_object_id($entity)] = [];
        }

        $this->saveBufferByOid[spl_object_id($entity)][$fieldName] = $value;
    }

    protected function afterUnload(Model $entity): void
    {
        $this->unsetId($entity);
        $this->unsetSaveBuffer($entity);
    }

    abstract public function afterLoad(Model $entity): void;

    public function beforeInsert(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        // the value for the master_field is set, so we are going to use existing record anyway
        if ($model->hasField($this->master_field) && $entity->get($this->master_field) !== null) {
            return;
        }

        $foreignModel = $this->getForeignModel();
        $foreignEntity = $foreignModel->createEntity()
            ->setMulti($this->getAndUnsetSaveBuffer($entity))
            /* ->set($this->foreign_field, null) */;
        $foreignEntity->save();

        $this->setId($entity, $foreignEntity->getId());

        if ($this->hasJoin()) {
            $this->getJoin()->setSaveBufferValue($entity, $this->master_field, $this->getId($entity));
        } else {
            $data[$this->master_field] = $this->getId($entity);
        }

        // $entity->set($this->master_field, $this->getId($entity)); // TODO needed? from array persistence
    }

    public function afterInsert(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $id = $this->hasJoin() ? $this->getJoin()->getId($entity) : $entity->getId();
        $this->assertReferenceIdNotNull($id);
        $this->setSaveBufferValue($entity, $this->foreign_field, $id); // TODO needed? from array persistence

        $foreignModel = $this->getForeignModel();
        $foreignEntity = $foreignModel->createEntity()
            ->setMulti($this->getAndUnsetSaveBuffer($entity))
            ->set($this->foreign_field, $id);
        $foreignEntity->save();

        $this->setId($entity, $entity->getId()); // TODO why is this here? it seems to be not needed
    }

    public function beforeUpdate(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if (!$this->issetSaveBuffer($entity)) {
            return;
        }

        $foreignModel = $this->getForeignModel();
        $foreignId = $this->reverse ? $entity->getId() : $entity->get($this->master_field);
        $this->assertReferenceIdNotNull($foreignId);
        $saveBuffer = $this->getAndUnsetSaveBuffer($entity);
        $foreignModel->atomic(function () use ($foreignModel, $foreignId, $saveBuffer) {
            $foreignModel = (clone $foreignModel)->addCondition($this->foreign_field, $foreignId);
            foreach ($foreignModel as $foreignEntity) {
                $foreignEntity->setMulti($saveBuffer);
                $foreignEntity->save();
            }
        });

        // $this->setId($entity, ??); // TODO needed? from array persistence
    }

    public function doDelete(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $foreignModel = $this->getForeignModel();
        $foreignId = $this->reverse ? $entity->getId() : $entity->get($this->master_field);
        $this->assertReferenceIdNotNull($foreignId);
        $foreignModel->atomic(function () use ($foreignModel, $foreignId) {
            $foreignModel = (clone $foreignModel)->addCondition($this->foreign_field, $foreignId);
            foreach ($foreignModel as $foreignEntity) {
                $foreignEntity->delete();
            }
        });

        $this->unsetId($entity); // TODO needed? from array persistence
    }
}
